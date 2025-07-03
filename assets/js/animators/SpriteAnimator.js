/**
 * SpriteAnimator - Handles sprite-sheet animations in canvas
 * Supports configurable playback speed, looping, scaling, and anchoring
 */
export class SpriteAnimator {
  constructor(canvas, options = {}) {
    this.canvas = canvas;
    this.context = canvas.getContext('2d');
    this.spriteSheet = null;
    this.frameDefs = [];
    this.currentFrameIndex = 0;
    this.elapsedTime = 0;
    this.isPlaying = false;
    this.lastFrameTime = 0;
    console.log(options);
    
    function resolveConfig(val, dim) {
      if (typeof val === 'string' && val.endsWith('%')) {
        return (parseFloat(val) / 100) * canvas[dim];
      }
      return val;
    }
    
    // Configuration options    
    this.loop = options.loop !== undefined ? options.loop : true;
    this.playbackSpeed = options.playbackSpeed || 1.0;
    this.scale = options.scale || 1.0;
    this.anchor = options.anchor || { x: 0, y: 0 };
    const _x = resolveConfig(options.position.x, 'width') || 0;
    const _y = resolveConfig(options.position.y, 'height') || 0;
    this.position = { x: _x, y: _y };
    this.visible = options.visible !== false;
    this.persistOnStop = options.persistOnStop !== true;
    
    // Animation state
    this.animationId = null;
    this.onComplete = options.onComplete || null;
    this.onFrameChange = options.onFrameChange || null;
    
    // Bind methods
    this.tick = this.tick.bind(this);
  }

  /**
   * Load sprite sheet and frame definitions
   * @param {string} sheetUrl - URL to sprite sheet image
   * @param {Array} frameDefs - Array of frame definitions

   */
  async load(sheetUrl, frameDefs) {
    return new Promise((resolve, reject) => {
      this.spriteSheet = new Image();
      this.spriteSheet.onload = () => {
        this.frameDefs = frameDefs.map(frame => ({
          x: frame.x * (frame.width || 64), // Convert grid coords to pixels if needed
          y: frame.y * (frame.height || 64),
          width: frame.width || 64,
          height: frame.height || 64,
          duration: frame.duration || 100
        }));
        resolve();
      };
      this.spriteSheet.onerror = () => {
        reject(new Error(`Failed to load sprite sheet: ${sheetUrl}`));
      };
      this.spriteSheet.src = sheetUrl;
    });
  }

  /**
   * Start playing the animation
   */
  play() {
    if (!this.spriteSheet || this.frameDefs.length === 0) {
      console.warn('SpriteAnimator: Cannot play - no sprite sheet or frames loaded');
      return;
    }
    
    this.isPlaying = true;
    this.lastFrameTime = performance.now();
    
    if (!this.animationId) {
      this.animationId = requestAnimationFrame(this.tick);
    }
  }

  /**
   * Pause the animation
   */
  pause() {
    this.isPlaying = false;
  }

  /**
   * Stop the animation and reset to first frame
   */
  stop() {
    this.isPlaying = false;
    this.currentFrameIndex = 0;
    this.elapsedTime = 0;

    if (!this.persistOnStop) {
      if (this.animationId) {
        cancelAnimationFrame(this.animationId);
        this.animationId = null;
      }
    }
  }

  /**
   * Hide the animator (stop rendering completely)
   */
  hide() {
    this.visible = false;
  }

  /**
   * Show the animator (resume rendering)
   */
  show() {
    this.visible = true;
  }

  /**
   * Seek to a specific frame or time
   * @param {number} frameOrTime - Frame index or time in milliseconds
   */
  seek(frameOrTime) {
    if (frameOrTime < this.frameDefs.length) {
      // Seeking by frame index
      this.currentFrameIndex = Math.floor(frameOrTime);
      this.elapsedTime = 0;
    } else {
      // Seeking by time
      let totalTime = 0;
      let targetFrame = 0;
      
      for (let i = 0; i < this.frameDefs.length; i++) {
        const frameDuration = this.frameDefs[i].duration / this.playbackSpeed;
        if (totalTime + frameDuration > frameOrTime) {
          targetFrame = i;
          this.elapsedTime = frameOrTime - totalTime;
          break;
        }
        totalTime += frameDuration;
      }
      
      this.currentFrameIndex = targetFrame;
    }
    
    this.renderFrame();
  }

  /**
   * Set playback speed multiplier
   * @param {number} speed - Speed multiplier (1.0 = normal)
   */
  setPlaybackSpeed(speed) {
    this.playbackSpeed = Math.max(0.1, speed);
  }

  /**
   * Set looping behavior
   * @param {boolean} loop - Whether to loop the animation
   */
  setLoop(loop) {
    this.loop = loop;
  }

  /**
   * Set scale factor
   * @param {number} scale - Uniform scale factor
   */
  setScale(scale) {
    this.scale = Math.max(0.1, scale);
  }
  
  /**
   * Set anchor point for drawing
   * @param {number} x - X anchor (0-1 for percentage, >1 for pixels)
   * @param {number} y - Y anchor (0-1 for percentage, >1 for pixels)
   */
  setAnchor(x, y) {
    this.anchor = { x, y };
  }

  /**
   * Set position for drawing
   * @param {number} x - X position
   * @param {number} y - Y position
   */
  setPosition(x, y) {
    this.position = { x, y };
  }

  /**
   * Main animation tick
   * @param {number} currentTime - Current timestamp from requestAnimationFrame
   */
  tick(currentTime) {
    if (this.visible) {
      this.renderFrame();
    }

    if (this.isPlaying) {
      const deltaTime = currentTime - this.lastFrameTime;
      this.lastFrameTime = currentTime;
      
      this.elapsedTime += deltaTime;
      
      const currentFrame = this.frameDefs[this.currentFrameIndex];
      const frameDuration = currentFrame.duration / this.playbackSpeed;
      
      if (this.elapsedTime >= frameDuration) {
        this.elapsedTime = 0;
        this.currentFrameIndex++;
        
        // Trigger frame change callback
        if (this.onFrameChange) {
          this.onFrameChange(this.currentFrameIndex);
        }
        
        // Handle end of animation
        if (this.currentFrameIndex >= this.frameDefs.length) {
          if (this.loop) {
            this.currentFrameIndex = 0;
          } else {
            this.currentFrameIndex = this.frameDefs.length - 1;
            this.isPlaying = false;
            
            if (this.onComplete) {
              this.onComplete();
            }
          }
        }
      }
    }

    // Continue the animation loop
    this.animationId = requestAnimationFrame(this.tick);
  }

  /**
   * Render the current frame to canvas
   */
  renderFrame() {
    if (!this.visible || !this.spriteSheet || !this.frameDefs[this.currentFrameIndex]) {     
      return;
    }
    this.context.clearRect(0, 0, this.canvas.width, this.canvas.height);

    const frame = this.frameDefs[this.currentFrameIndex];
    const scaledWidth = frame.width * this.scale;
    const scaledHeight = frame.height * this.scale;

    // Calculate anchor offset
    let anchorX, anchorY;
    if (this.anchor.x <= 1) {
      anchorX = scaledWidth * this.anchor.x;
    } else {
      anchorX = this.anchor.x;
    }
    
    if (this.anchor.y <= 1) {
      anchorY = scaledHeight * this.anchor.y;
    } else {
      anchorY = this.anchor.y;
    }

    // Draw the sprite frame
    this.context.drawImage(
      this.spriteSheet,
      frame.x, frame.y, frame.width, frame.height,
      this.position.x - anchorX, this.position.y - anchorY,
      scaledWidth, scaledHeight
    );
  }

  /**
   * Get current animation state
   * @returns {Object} Current state information
   */
  getState() {
    return {
      isPlaying: this.isPlaying,
      currentFrame: this.currentFrameIndex,
      totalFrames: this.frameDefs.length,
      elapsedTime: this.elapsedTime,
      playbackSpeed: this.playbackSpeed,
      loop: this.loop,
      visible: this.visible
    };
  }

  /**
   * Destroy the animator and clean up resources
   */
  destroy() {
    this.visible = false;
    this.isPlaying = false;
    if (this.animationId) {
      cancelAnimationFrame(this.animationId);
      this.animationId = null;
    }    
    this.spriteSheet = null;
    this.frameDefs = [];
    this.onComplete = null;
    this.onFrameChange = null;
  }
}
