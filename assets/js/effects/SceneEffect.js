/**
 * SceneEffect - Manages background animations, particle emitters, and transitions
 * Uses a timeline-based approach for coordinating multiple effects
 */
export class SceneEffect {
  constructor(canvas, effectConfigs = []) {
    this.canvas = canvas;
    this.context = canvas.getContext('2d');
    this.effects = [];
    this.animators = [];
    this.isPlaying = false;
    this.startTime = 0;
    this.pausedTime = 0;
    this.currentTime = 0;
    
    // Animation loop
    this.animationId = null;
    this.tick = this.tick.bind(this);
    
    // Initialize effects from config
    if (effectConfigs.length > 0) {
      this.loadEffects(effectConfigs);
    }
  }

  /**
   * Load effects from configuration array
   * @param {Array} effectConfigs - Array of effect configurations
   */
  loadEffects(effectConfigs) {
    for (const config of effectConfigs) {
      this.addEffect(config);
    }
  }

  /**
   * Add an effect to the scene
   * @param {Object} effectConfig - Effect configuration
   */
  addEffect(effectConfig) {
    const effect = this.createEffect(effectConfig);
    if (effect) {
      this.effects.push(effect);
    }
  }

  /**
   * Add a sprite animator to be managed by this scene
   * @param {SpriteAnimator} animator - Sprite animator instance
   */
  addAnimator(animator) {
    this.animators.push(animator);
  }

  /**
   * Create an effect instance based on configuration
   * @param {Object} config - Effect configuration
   * @returns {EffectInstance|null} Created effect instance
   */
  createEffect(config) {
    const effectClass = this.getEffectClass(config.type);
    if (!effectClass) {
      console.warn(`SceneEffect: Unknown effect type: ${config.type}`);
      return null;
    }

    return new effectClass(this.canvas, {
      startTime: config.start || 0,
      duration: config.duration || Infinity,
      params: config.params || {}
    });
  }

  /**
   * Get effect class by type name
   * @param {string} type - Effect type name
   * @returns {Function|null} Effect class constructor
   */
  getEffectClass(type) {
    const effectTypes = {
      'ParallaxLayer': ParallaxLayerEffect,
      'ParticleEmitter': ParticleEmitterEffect,
      'Transition': TransitionEffect,
      'BackgroundGradient': BackgroundGradientEffect,
      'CanvasShake': CanvasShakeEffect,
      'GlitchOverlay': GlitchOverlayEffect,
      'Rift': RiftEffect,
      'WrenchDrop': WrenchDropEffect
    };

    return effectTypes[type] || null;
  }

  /**
   * Start playing all effects
   */
  play() {
    this.isPlaying = true;
    this.startTime = performance.now() - this.pausedTime;
    
    if (!this.animationId) {
      this.animationId = requestAnimationFrame(this.tick);
    }
  }

  /**
   * Pause all effects
   */
  pause() {
    this.isPlaying = false;
    this.pausedTime = this.currentTime;
  }

  /**
   * Stop all effects and reset
   */
  stop() {
    this.isPlaying = false;
    this.pausedTime = 0;
    this.currentTime = 0;
    
    // Stop all effects
    for (const effect of this.effects) {
      effect.stop();
    }
    
    if (this.animationId) {
      cancelAnimationFrame(this.animationId);
      this.animationId = null;
    }
  }

  /**
   * Seek to a specific time
   * @param {number} time - Time in milliseconds
   */
  seek(time) {
    this.currentTime = time;
    this.pausedTime = time;
    
    // Update all effects to the new time
    for (const effect of this.effects) {
      effect.seek(time);
    }
  }

  /**
   * Main animation tick
   * @param {number} timestamp - Current timestamp from requestAnimationFrame
   */
  tick(timestamp) {
    if (this.isPlaying) {
      this.currentTime = timestamp - this.startTime;
      
      // Clear canvas for this frame
      this.context.clearRect(0, 0, this.canvas.width, this.canvas.height);
      console.log("SceneEffect tick - cleared canvas, rendering", this.effects.length, "effects and", this.animators.length, "animators");
      
      // Update and render all effects
      for (const effect of this.effects) {
        effect.update(this.currentTime);
      }
      
      // Update active sprite animators
      for (const animator of this.animators) {
        if (animator.isPlaying || (animator.persistOnStop && animator.hasPlayed)) {
          animator.update(timestamp);
          animator.renderFrame();
        }
      }
    }
    
    this.animationId = requestAnimationFrame(this.tick);
  }

  /**
   * Destroy the scene effect and clean up resources
   */
  destroy() {
    this.stop();
    
    for (const effect of this.effects) {
      if (effect.destroy) {
        effect.destroy();
      }
    }
    
    this.effects = [];
    this.animators = [];
  }
}

/**
 * Base class for effect instances
 */
class EffectInstance {
  constructor(canvas, options = {}) {
    this.canvas = canvas;
    this.context = canvas.getContext('2d');
    this.startTime = options.startTime || 0;
    this.duration = options.duration || Infinity;
    this.params = options.params || {};
    this.isActive = false;
    this.hasStarted = false;
    this.hasEnded = false;
  }

  /**
   * Update the effect for the current time
   * @param {number} currentTime - Current scene time in milliseconds
   */
  update(currentTime) {
    const relativeTime = currentTime - this.startTime;
    
    // Check if effect should be active
    if (relativeTime >= 0 && relativeTime <= this.duration) {
      if (!this.hasStarted) {
        this.start();
        this.hasStarted = true;
      }
      this.isActive = true;
      this.render(relativeTime);
    } else if (this.isActive && relativeTime > this.duration) {
      this.end();
      this.isActive = false;
      this.hasEnded = true;
    }
  }

  /**
   * Start the effect
   */
  start() {
    // Override in subclasses
  }

  /**
   * Render the effect
   * @param {number} relativeTime - Time relative to effect start
   */
  render(relativeTime) {
    // Override in subclasses
  }

  /**
   * End the effect
   */
  end() {
    // Override in subclasses
  }

  /**
   * Stop the effect
   */
  stop() {
    this.isActive = false;
    this.hasStarted = false;
    this.hasEnded = false;
  }

  /**
   * Seek to a specific time
   * @param {number} time - Scene time in milliseconds
   */
  seek(time) {
    const relativeTime = time - this.startTime;
    
    if (relativeTime >= 0 && relativeTime <= this.duration) {
      if (!this.hasStarted) {
        this.start();
        this.hasStarted = true;
      }
      this.isActive = true;
      this.render(relativeTime);
    } else {
      this.isActive = false;
    }
  }
}

/**
 * Parallax background layer effect
 */
class ParallaxLayerEffect extends EffectInstance {
  constructor(canvas, options) {
    super(canvas, options);
    this.image = null;
    this.speed = this.params.speed || 0.5;
    this.direction = this.params.direction || 'horizontal';
    this.offset = 0;
    
    this.loadImage();
  }

  async loadImage() {
    if (!this.params.imageUrl) return;
    
    this.image = new Image();
    // DEBUG: this.image.onload = () => { console.log("Parallax image loaded:", this.image.src); };
    this.image.src = this.params.imageUrl;
  }

  render(relativeTime) {
    if (!this.image || !this.image.complete) return;

    /* DEBUG:
    console.log("Parallax render:", {
        imageSize: `${this.image.width}x${this.image.height}`,
        canvasSize: `${this.canvas.width}x${this.canvas.height}`,
        relativeTime,
        speed: this.speed,
        direction: this.direction
        });
    */
    
    const scaleY = this.canvas.height / this.image.height;
    const scaledWidth = this.image.width * scaleY;
    const scaledHeight = this.canvas.height;
    
    if (this.direction === 'horizontal') {
      // Calculate parallax offset
      this.offset = (relativeTime * this.speed * 0.1) % this.image.width;

      // Draw tiles to fill canvas width plus one for seamless scrolling
      const tilesX = Math.ceil(this.canvas.width / scaledWidth) + 1;
      
      for (let i = 0; i < tilesX; i++) {
        const x = (i * scaledWidth) - this.offset;
        this.context.drawImage(this.image, x, 0, scaledWidth, scaledHeight);
      }
    }
    else {
      const yOffset = (relativeTime * this.speed * 0.1) % this.image.height;

      const scaleX = this.canvas.width / this.image.width;
      const verticalScaledHeight = this.image.height * scaleX;
      const verticalScaledWidth = this.canvas.width;

      const tilesY = Math.ceil(this.canvas.height / verticalScaledHeight) + 1;
      for (let i = 0; i < tilesY; i++) {
        const y = (i * this.image.height) - yOffset;
        this.context.drawImage(this.image, 0, y, verticalScaledWidth, verticalScaledHeight);
      }
    }
  }
}

/**
 * Particle emitter effect
 */
class ParticleEmitterEffect extends EffectInstance {
  constructor(canvas, options) {
    super(canvas, options);
    this.particles = [];
    this.emissionRate = this.params.rate || 30;
    this.particleLifespan = this.params.lifespan || 2000;
    this.lastEmissionTime = 0;
    
    this.particleConfig = {
      size: this.params.size || 2,
      color: this.params.color || '#ffffff',
      velocity: this.params.velocity || { x: 0, y: -50 },
      gravity: this.params.gravity || { x: 0, y: 20 },
      fadeOut: this.params.fadeOut !== false
    };
  }

  render(relativeTime) {
    // Emit new particles
    const timeSinceLastEmission = relativeTime - this.lastEmissionTime;
    const emissionInterval = 1000 / this.emissionRate;
    
    if (timeSinceLastEmission >= emissionInterval) {
      this.emitParticle();
      this.lastEmissionTime = relativeTime;
    }
    
    // Update and render particles
    this.context.save();
    
    for (let i = this.particles.length - 1; i >= 0; i--) {
      const particle = this.particles[i];
      const age = relativeTime - particle.birthTime;
      
      if (age > this.particleLifespan) {
        this.particles.splice(i, 1);
        continue;
      }
      
      // Update particle position
      particle.x += particle.vx * 0.016; // Assume ~60fps
      particle.y += particle.vy * 0.016;
      particle.vx += this.particleConfig.gravity.x * 0.016;
      particle.vy += this.particleConfig.gravity.y * 0.016;
      
      // Render particle
      const alpha = this.particleConfig.fadeOut ? 1 - (age / this.particleLifespan) : 1;
      this.context.globalAlpha = alpha;
      this.context.fillStyle = this.particleConfig.color;
      this.context.beginPath();
      this.context.arc(particle.x, particle.y, this.particleConfig.size, 0, Math.PI * 2);
      this.context.fill();
    }
    
    this.context.restore();
  }

  emitParticle() {
    const area = this.params.area || {
      x: this.canvas.width / 2 - 50,
      y: this.canvas.height / 2 - 50,
      width: 100,
      height: 100
    };
    
    const resolve = (val, dim) => {
      if (typeof val === 'string' && val.endsWith('%')) {
        return (parseFloat(val) / 100) * dim;
      }
      return val;
    };


    const x = resolve(area.x, this.canvas.width) + Math.random() * resolve(area.width, this.canvas.width);
    const y = resolve(area.y, this.canvas.height) + Math.random() * resolve(area.height, this.canvas.height);
    
    this.particles.push({
      x,
      y,
      vx: this.particleConfig.velocity.x + (Math.random() - 0.5) * 20,
      vy: this.particleConfig.velocity.y + (Math.random() - 0.5) * 20,
      birthTime: this.lastEmissionTime
    });
  }
}

/**
 * Transition effect (fades, wipes, etc.)
 */
class TransitionEffect extends EffectInstance {
  render(relativeTime) {
    const progress = Math.min(relativeTime / this.duration, 1);
    const style = this.params.style || 'fadeIn';
    const color = this.params.color || 'black';
    
    this.context.save();
    
    switch (style) {
      case 'fadeIn':
        this.context.globalAlpha = 1 - progress;
        this.context.fillStyle = color;
        this.context.fillRect(0, 0, this.canvas.width, this.canvas.height);
        break;
        
      case 'fadeOut':
        this.context.globalAlpha = progress;
        this.context.fillStyle = color;
        this.context.fillRect(0, 0, this.canvas.width, this.canvas.height);
        break;
        
      case 'wipeLeft':
        const wipeWidth = this.canvas.width * progress;
        this.context.fillStyle = color;
        this.context.fillRect(0, 0, wipeWidth, this.canvas.height);
        break;
        
      case 'circle':
        const maxRadius = Math.sqrt(this.canvas.width ** 2 + this.canvas.height ** 2) / 2;
        const radius = maxRadius * progress;
        this.context.fillStyle = color;
        this.context.fillRect(0, 0, this.canvas.width, this.canvas.height);
        this.context.globalCompositeOperation = 'destination-out';
        this.context.beginPath();
        this.context.arc(this.canvas.width / 2, this.canvas.height / 2, radius, 0, Math.PI * 2);
        this.context.fill();
        break;
    }
    
    this.context.restore();
  }
}

/**
 * Background gradient effect
 */
class BackgroundGradientEffect extends EffectInstance {
  render(relativeTime) {
    const colors = this.params.colors || ['#000033', '#000066'];
    const direction = this.params.direction || 'vertical';
    
    let gradient;
    if (direction === 'vertical') {
      gradient = this.context.createLinearGradient(0, 0, 0, this.canvas.height);
    } else {
      gradient = this.context.createLinearGradient(0, 0, this.canvas.width, 0);
    }
    
    colors.forEach((color, index) => {
      gradient.addColorStop(index / (colors.length - 1), color);
    });
    
    this.context.fillStyle = gradient;
    this.context.fillRect(0, 0, this.canvas.width, this.canvas.height);
  }
}

/**
 * Canvas shake effect
 */
class CanvasShakeEffect extends EffectInstance {
  render(relativeTime) {
    const intensity = this.params.intensity === 'medium' ? 2 : 4;
    const dx = (Math.random() - 0.5) * intensity;
    const dy = (Math.random() - 0.5) * intensity;
    this.context.translate(dx, dy);
  }
}

/**
 * Glitch Overlay Effect
 */
class GlitchOverlayEffect extends EffectInstance {
  render(relativeTime) {
    const alpha = Math.random() * 0.2 + 0.1;
    this.context.save();
    this.context.globalAlpha = alpha;
    this.context.fillStyle = 'rgba(255, 0, 0, 0.1)';
    this.context.fillRect(0, 0, this.canvas.width, this.canvas.height);
    this.context.restore();
  }
}


/**
 * Rift effect
 */
class RiftEffect extends EffectInstance {
  resolveCoord(value, dimension) {
    if (typeof value === 'string' && value.includes('%')) {
      const [percent, offset] = value.split('+').map(v => v.trim());
      let base = parseFloat(percent) / 100 * dimension;
      let off = offset ? parseFloat(offset) : 0;
      return base + off;
    }
    return value;
  }

  start() {
    this.x = this.resolveCoord(this.params.x, this.canvas.width);
    this.y = this.resolveCoord(this.params.y, this.canvas.height);
    this.width = this.params.width || 100;
    this.height = this.params.height || 20;
    this.pulse = 0;
  }

  render(relativeTime) {
    this.pulse += 0.05; // Smooth pulse

    const alpha = 0.5 + Math.sin(this.pulse) * 0.3;
    const jitterX = (Math.random() - 0.5) * 4;
    const jitterY = (Math.random() - 0.5) * 4;

    this.context.save();
    this.context.globalAlpha = alpha;

    // Outer glow
    this.context.fillStyle = '#ff0066';
    this.context.fillRect(this.x + jitterX, this.y + jitterY, this.width, this.height);

    // Inner core
    this.context.fillStyle = '#0066ff';
    this.context.fillRect(
      this.x + jitterX + 4,
      this.y + jitterY + 4,
      this.width - 8,
      this.height - 8
    );

    this.context.restore();
  }
}

class oldRiftEffect extends EffectInstance {
  resolveCoord(value, dimension) {
    if (typeof value === 'string' && value.includes('%')) {
      const [percent, offset] = value.split('+').map(v => v.trim());
      let base = parseFloat(percent) / 100 * dimension;
      let off = offset ? parseFloat(offset) : 0;
      return base + off;
    }
    return value;
  }
  
  start() {
    this.pulse = Math.random() * Math.PI * 2;
    this.x = this.resolveCoord(this.params.x, this.canvas.width);
    this.y = this.resolveCoord(this.params.y, this.canvas.height);
    this.width = this.params.width || 100;
    this.height = this.params.height || 20;
  }

  render(relativeTime) {
    this.pulse += 0.1;
    const alpha = 0.3 + Math.sin(this.pulse) * 0.2;

    this.context.save();
    this.context.globalAlpha = alpha;

    this.context.strokeStyle = 'yellow';
    this.context.strokeRect(this.x, this.y, this.width, this.height);

    this.context.fillStyle = '#ff0066';
    this.context.fillRect(this.x, this.y, this.width, this.height);

    this.context.fillStyle = '#0066ff';
    this.context.fillRect(this.x + 20, this.y + 20, this.width - 40, this.height - 40);

    this.context.restore();
  }
}

/**
 * Wrench drop effect
 */
class WrenchDropEffect extends EffectInstance {
  start() {
    this.y = this.canvas.height / 2 + 55;
    this.x = this.canvas.width / 2 + 30;
  }

  render(relativeTime) {
    this.y += 0.5; // slow fall
    this.context.fillStyle = '#888';
    this.context.fillRect(this.x, this.y, 8, 3);
    this.context.fillRect(this.x + 2, this.y - 3, 4, 9);
  }
}

