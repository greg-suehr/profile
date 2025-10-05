/**
 * Universal Scene Bootstrap
 * Loads scene configuration and initializes animators and effects
 */
import { SpriteAnimator } from './animators/SpriteAnimator.js';
import { SceneEffect } from './effects/SceneEffect.js';

class SceneBootstrap {
  constructor() {
    this.canvas = null;
    this.sceneId = null;
    this.config = null;
    this.effectManager = null;
    this.spriteAnimators = new Map();
    this.dialogueTimeouts = [];
    this.activeAnimations = new Map(); // Track active position animations
  }
  
  resolveConfig(val, dim, canvas) {
    if (typeof val === 'string' && val.endsWith('%')) {
      const percent = parseFloat(val);
      if (dim === 'x') return (percent / 100) * canvas.width;
      if (dim === 'y') return (percent / 100) * canvas.height;
    }
    return val !== undefined ? val : null;
  }

  /**
   * Initialize the scene
   */
  async init() {
    try {
      // Find canvas and get scene ID
      this.canvas = document.getElementById('screen') || document.querySelector('canvas[data-scene-id]');
      if (!this.canvas) {
        throw new Error('No canvas element found with id="screen" or data-scene-id attribute');
      }

      this.canvas.width = window.innerWidth;
      this.canvas.height = window.innerHeight;

      this.sceneId = this.canvas.dataset.sceneId;
      if (!this.sceneId) {
        throw new Error('Canvas element missing data-scene-id attribute');
      }

      // Load scene configuration
      await this.loadSceneConfig();

      // Initialize scene effects
      window.addEventListener('effect:callback', (e) => {
        const callbackName = e.detail.callback;
        if (callbackName === 'revealSite' && typeof this.revealSite === 'function') {
          this.revealSite();
        }
      });

      window.addEventListener('video:ended', () => {
        console.log('[Scene] video:ended → revealSite()');
        this.revealSite();
      });
      
      this.initializeSceneEffects();

      // Initialize sprite animators
      await this.initializeSpriteAnimators();

      // Setup dialogue timeline
      this.setupDialogueTimeline();

      console.log(`Scene "${this.sceneId}" loaded and ready - waiting for user interaction`);
    } catch (error) {
      console.error('Failed to initialize scene:', error);
      this.showError(error.message);
    }
  }

  /**
   * Load scene configuration from JSON
   */
  async loadSceneConfig() {
    try {
      const response = await fetch(`/assets/scenes/${this.sceneId}.json`);
      if (!response.ok) {
        throw new Error(`Failed to load scene config: ${response.status} ${response.statusText}`);
      }
      this.config = await response.json();
    } catch (error) {
      throw new Error(`Error loading scene configuration: ${error.message}`);
    }
  }

  /**
   * Initialize scene effects manager
   */
  initializeSceneEffects() {
    const effectConfigs = this.config.sceneEffects || [];
    this.effectManager = new SceneEffect(this.canvas, effectConfigs);
  }

  /**
   * Initialize all sprite animators from configuration
   */
  async initializeSpriteAnimators() {
    const animatorConfigs = this.config.spriteAnimators || [];
    
    for (const config of animatorConfigs) {
      try {
        const animator = new SpriteAnimator(this.canvas, config.options);
        await animator.load(config.sheetUrl, config.frameDefs);
        
        // Set position if specified
        const pos = config.options?.position;
        
        if (config.position) {
          const resolvedX = resolveConfig(pos.x, this.canvas.width);
          const resolvedY = resolveConfig(pos.y, this.canvas.height);          
          animator.setPosition(config.position.x, config.position.y);
        } else {
          // Default to center of canvas
          animator.setPosition(this.canvas.width / 2, this.canvas.height / 2);
        }
        
        // Store animator with its name for later reference
        this.spriteAnimators.set(config.name, animator);
        
        // Add to effect manager for coordinated rendering
        this.effectManager.addAnimator(animator);
        
        // DEBUG: console.log(`Loaded sprite animator: ${config.name}`);
      } catch (error) {
        console.error(`Failed to load sprite animator "${config.name}":`, error);
      }
    }
  }

  /**
   * Setup dialogue timeline from configuration
   */
  setupDialogueTimeline() {
    const timeline = this.config.dialogueTimeline || [];
    
    for (const item of timeline) {
      const timeout = setTimeout(() => {
        try {
          const element = document.querySelector(item.selector);
          if (element) {
            switch (item.action) {
              case 'show':
                element.classList.remove('hidden');
                element.style.display = 'block';
                break;
              case 'hide':
                element.classList.add('hidden');
                element.style.display = 'none';
                break;
              case 'fadeIn':
                element.style.opacity = '0';
                element.classList.remove('hidden');
                element.style.display = 'block';
                element.style.transition = 'opacity 0.5s ease-in-out';
                setTimeout(() => element.style.opacity = '1', 50);
                break;
              case 'addClass':
                if (item.className) {
                  element.classList.add(item.className);
                }
                break;
              case 'removeClass':
                if (item.className) {
                  element.classList.remove(item.className);
                }
                break;
            }
          } else {
            console.warn(`Dialogue timeline: Element not found for selector "${item.selector}"`);
          }
        } catch (error) {
          console.error('Error in dialogue timeline:', error);
        }
      }, item.after);
      
      this.dialogueTimeouts.push(timeout);
    }
  }

  /**
   * Start the scene (play effects and animators)
   */
  startScene() {
    // Start scene effects
    this.effectManager.play();
    
    // Start sprite animators that should auto-play
    for (const [name, animator] of this.spriteAnimators) {
      const config = this.config.spriteAnimators.find(cfg => cfg.name === name);
      if (!config.options || config.options.autoPlay !== false) {
        animator.play();
      }
    }

   // Trigger auto sequences
    const autoSequences = this.config.autoSequences || [];
    for (const sequenceName of autoSequences) {
      this.triggerSequence(sequenceName);
    }
  }

  /** 

  /**
   * Get a sprite animator by name
   * @param {string} name - Animator name
   * @returns {SpriteAnimator|null}
   */
  getAnimator(name) {
    return this.spriteAnimators.get(name) || null;
  }

  /**
   * Trigger a specific animation sequence
   * @param {string} sequence - Sequence name from config
   */
  triggerSequence(sequence) {
    const sequences = this.config.sequences || {};
    const sequenceConfig = sequences[sequence];
    
    if (!sequenceConfig) {
      console.warn(`Sequence "${sequence}" not found in configuration`);
      return;
    }
    
    // Execute sequence steps
    for (const step of sequenceConfig.steps || []) {
      setTimeout(() => {
        this.executeSequenceStep(step);
      }, step.delay || 0);
    }
  }

  revealSite() {
    console.log("ANIMATION COMPLETE - Starting site reveal transition");
    
    const site = document.getElementById('litmas-site-structure');
    const canvas = this.canvas;
    const audio    = document.getElementById('litmas-music');
    const altAudio = document.getElementById('litmas-sfx-enter');
    
    if (!site || !canvas) {
      console.warn('Required elements not found for site reveal');
      return;
    }
    
    // Start music fade out
    if (audio && !audio.paused) {
      this.fadeOutMusic(audio, 1500); // 1.5 second fade
    }
    
    if (altAudio && !altAudio.paused) {
      this.fadeOutMusic(altAudio, 6000); // 6.0 second fade
    }    
    
    // Prepare site content (hidden but ready)
    site.style.opacity = '0';
    site.style.transition = 'opacity 2s ease-in-out';
    site.classList.remove('hidden');
    
    // Start canvas fade out
    canvas.style.transition = 'opacity 1.5s ease-out';
    canvas.style.opacity = '0';
    
    // After canvas fades out, show site content
    setTimeout(() => {
      // Fade in site content
      site.style.opacity = '1';
      
      // After site content is visible, clean up canvas
      setTimeout(() => {
        this.stop(); // Stop all animations
        canvas.style.display = 'none'; // Hide canvas completely
        
        // Switch body class from canvas-mode to normal
        document.body.classList.remove('canvas-mode');
        
        console.log("Site reveal transition complete");
      }, 2000); // Wait for site fade-in to complete
      
    }, 1500); // Wait for canvas fade-out to complete
    console.log("ANIMATION COMPLETE");
  }

  /**
   * Smooth music fade out helper method
   * @param {HTMLAudioElement} audioElement - The audio element to fade
   * @param {number} duration - Fade duration in milliseconds
   */
  fadeOutMusic(audioElement, duration = 1500) {
    console.log("Fading...", audioElement);
    const startVolume = audioElement.volume;
    const fadeSteps = 50;
    const stepTime = duration / fadeSteps;
    const volumeStep = startVolume / fadeSteps;
    
    let currentStep = 0;
    
    const fadeInterval = setInterval(() => {
      currentStep++;
      const newVolume = Math.max(0, startVolume - (volumeStep * currentStep));
    audioElement.volume = newVolume;
      
      if (currentStep >= fadeSteps || newVolume <= 0) {
        clearInterval(fadeInterval);
        audioElement.pause();
        audioElement.currentTime = 0; // Reset to beginning
        audioElement.volume = startVolume; // Reset volume for next time
        console.log("Music fade out complete");
      }
    }, stepTime);
  }

  /**
   * Execute a single sequence step
   * @param {Object} step - Sequence step configuration
   */
  executeSequenceStep(step) {
    switch (step.type) {
      case 'playAnimator':
        const animator = this.getAnimator(step.animator);
        if (animator) {
          if (step.onComplete) {
            console.log("attach onComplete for ", step);
            animator.onComplete = () => {
              if (typeof step.onComplete === 'string' && this[step.onComplete]) {
                this[step.onComplete]();
              }
            };
          } else {
            console.log("no onComplete for ", step);
          }
          animator.play();
          
          if (step.audio) {
            const audioElement = document.getElementById(step.audio);
            if (audioElement) {
              try {
                audioElement.currentTime = 0;
                const bgMusic = document.getElementById('litmas-music');
                if (bgMusic) {
                  let fade = setInterval(() => {
                    if (bgMusic.volume > 0.05) {
                      bgMusic.volume -= 0.05;
                    } else {
                      bgMusic.volume = 0;
                      bgMusic.pause();
                      clearInterval(fade);
                    }
                  }, 100);
                }
                audioElement.play().catch(err => {
                  console.warn(`Audio "${step.audio}" could not be played:`, err);
                });
              } catch (err) {
                console.error(`Error playing audio "${step.audio}":`, err);
              }
            } else {
              console.warn(`Audio element "${step.audio}" not found in DOM.`);
            }
          }
        }
        break;
        
      case 'stopAnimator':
        const stopAnimator = this.getAnimator(step.animator);
        if (stopAnimator) {
          stopAnimator.stop();
        }
        break;
        
      case 'setAnimatorPosition':
        const posAnimator = this.getAnimator(step.animator);
        if (posAnimator && step.position) {
          posAnimator.setPosition(step.position.x, step.position.y);
        }
        break;

      case 'animatePosition':
        this.animateAnimatorPosition(step);
        break;
        
      case 'addEffect':
      if (step.effect) {
        this.effectManager.addEffect({          
          'type': step.effect,
          'start': step.delay || step.start || 0,
          'duration': step.duration || 0,
          'params' : step.params || {}
        });
        }
        break;
        
      default:
        console.warn(`Unknown sequence step type: ${step.type}`);
    }
  }

  /**
   * Animate an animator's position over time
   * @param {Object} step - Animation step configuration
   */
  animateAnimatorPosition(step) {
    const animator = this.getAnimator(step.animator);
    if (!animator) {
      console.warn(`Animator "${step.animator}" not found for position animation`);
      return;
    }

    const animationId = `${step.animator}_position_${Date.now()}`;
    const startTime = performance.now();
    const duration = step.duration || 1000;

    const fromPosX = resolveConfig(step.from?.x, 'x', animator.canvas);
    const fromPosY = resolveConfig(step.from?.y, 'y', animator.canvas);
    const fromPos = {
      x: fromPosX !== null ? fromPosX : animator.position.x,
      y: fromPosY !== null ? fromPosY : animator.position.y,
    };
    
    const toPosX = resolveConfig(step.to?.x, 'x', animator.canvas);
    const toPosY = resolveConfig(step.to?.y, 'y', animator.canvas);
    const toPos = {
      x: toPosX !== null ? toPosX : fromPos.x,
      y: toPosY !== null ? toPosY : fromPos.y,
    };
        
    const easing = step.easing || 'linear';

    // Store animation reference for cleanup
    const animation = {
      id: animationId,
      animator: step.animator,
      startTime,
      duration,
      fromPos,
      toPos,
      easing,
      onComplete: step.onComplete
    };

    this.activeAnimations.set(animationId, animation);

    const animate = (currentTime) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);

      // Apply easing function
      const easedProgress = this.applyEasing(progress, easing);

      // Calculate current position
      const currentX = fromPos.x + (toPos.x - fromPos.x) * easedProgress;
      const currentY = fromPos.y + (toPos.y - fromPos.y) * easedProgress;

      // Update animator position
      animator.setPosition(currentX, currentY);

      if (progress < 1) {
        // Continue animation
        requestAnimationFrame(animate);
      } else {
        // Animation complete
        this.activeAnimations.delete(animationId);
        
        // Trigger completion callback or sequence
        if (animation.onComplete) {
          if (typeof animation.onComplete === 'string') {
            // Trigger another sequence
            this.triggerSequence(animation.onComplete);
          } else if (typeof animation.onComplete === 'function') {
            // Execute callback function
            animation.onComplete();
          }
        }
      }
    };

    requestAnimationFrame(animate);
  }

  /**
   * Apply easing function to progress value
   * @param {number} progress - Linear progress (0-1)
   * @param {string} easing - Easing function name
   * @returns {number} Eased progress value
   */
  applyEasing(progress, easing) {
    switch (easing) {
      case 'easeIn':
        return progress * progress;
      case 'easeOut':
        return 1 - Math.pow(1 - progress, 2);
      case 'easeInOut':
        return progress < 0.5 
          ? 2 * progress * progress 
          : 1 - Math.pow(-2 * progress + 2, 2) / 2;
      case 'bounce':
        if (progress < 1/2.75) {
          return 7.5625 * progress * progress;
        } else if (progress < 2/2.75) {
          return 7.5625 * (progress -= 1.5/2.75) * progress + 0.75;
        } else if (progress < 2.5/2.75) {
          return 7.5625 * (progress -= 2.25/2.75) * progress + 0.9375;
        } else {
          return 7.5625 * (progress -= 2.625/2.75) * progress + 0.984375;
        }
      case 'elastic':
        if (progress === 0) return 0;
        if (progress === 1) return 1;
        return -Math.pow(2, 10 * (progress - 1)) * Math.sin((progress - 1.1) * 5 * Math.PI);
      case 'linear':
      default:
        return progress;
    }
  }

  /**
   * Stop a specific position animation
   * @param {string} animatorName - Name of the animator
   */
  stopPositionAnimation(animatorName) {
    for (const [id, animation] of this.activeAnimations) {
      if (animation.animator === animatorName) {
        this.activeAnimations.delete(id);
        console.log(`Stopped position animation for animator: ${animatorName}`);
        break;
      }
    }
  }

  /**
   * Animate arbitrary HTML content, but in the canvas!
   * @param {string} elementId - The value of id attribute of the root DOM item to display
   */
  renderHtmlToCanvas(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    
    const ctx = this.canvas.getContext('2d');
    ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    
    // Style defaults
    const font = "20px sans-serif";
    const lineHeight = 28;
    const maxWidth = this.canvas.width - 40;
    let x = 20;
    let y = 50;
    
    for (const child of el.children) {
      const text = child.innerText || child.textContent;
      if (!text) continue;
      
      // You can improve this with actual HTML-style mapping later
      ctx.font = child.tagName === "H2" ? "bold 28px sans-serif" : font;
      ctx.fillStyle = "#FBD75F";
      ctx.textAlign = "left";
      
      // Basic line wrapping
      const words = text.split(" ");
      let line = "";
      for (let n = 0; n < words.length; n++) {
        const testLine = line + words[n] + " ";
        const metrics = ctx.measureText(testLine);
        if (metrics.width > maxWidth && n > 0) {
          ctx.fillText(line, x, y);
          line = words[n] + " ";
          y += lineHeight;
        } else {
          line = testLine;
        }
      }
      ctx.fillText(line, x, y);
      y += lineHeight + 10;
    }
  }

  /**
   * Pause the entire scene
   */
  pause() {
    this.effectManager.pause();
    for (const animator of this.spriteAnimators.values()) {
      animator.pause();
    }
    // Note: Position animations continue running during pause
    // You could extend this to pause position animations too if needed
  }

  /**
   * Resume the scene
   */
  resume() {
    this.effectManager.play();
    for (const animator of this.spriteAnimators.values()) {
      animator.play();
    }
  }

  /**
   * Stop the scene and clean up
   */
  stop() {
    // Clear dialogue timeouts
    for (const timeout of this.dialogueTimeouts) {
      clearTimeout(timeout);
    }
    this.dialogueTimeouts = [];
    
    // Stop all position animations
    this.activeAnimations.clear();
    
    // Stop and destroy scene effects
    if (this.effectManager) {
      this.effectManager.destroy();
    }
    
    // Stop and destroy sprite animators
    for (const animator of this.spriteAnimators.values()) {
      animator.destroy();
    }
    this.spriteAnimators.clear();
  }

  /**
   * Show error message to user
   * @param {string} message - Error message
   */
  showError(message) {
    // Create error overlay
    const errorDiv = document.createElement('div');
    errorDiv.style.cssText = `
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: #ff4444;
      color: white;
      padding: 20px;
      border-radius: 8px;
      font-family: Arial, sans-serif;
      z-index: 9999;
      max-width: 400px;
      text-align: center;
    `;
    errorDiv.innerHTML = `
      <h3>Scene Loading Error</h3>
      <p>${message}</p>
      <button onclick="this.parentElement.remove()" style="
        background: white;
        color: #ff4444;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 10px;
      ">Close</button>
    `;
    document.body.appendChild(errorDiv);
  }
}

// Global scene bootstrap instance
let sceneBootstrap = null;

// Initialize scene configuration but don't start animations yet
async function prepareScene() {
  sceneBootstrap = new SceneBootstrap();
  
  try {
    await sceneBootstrap.init(); 
  } catch (error) {
    console.error('Failed to prepare scene:', error);
    sceneBootstrap.showError(error.message);
  }
}

// Modified startScene method - add this to your SceneBootstrap class
SceneBootstrap.prototype.startSceneAnimations = function() {
  console.log("Starting scene animations after user interaction");

  // Start scene effects
  this.effectManager.play();

  // Start sprite animators that should auto-play
  for (const [name, animator] of this.spriteAnimators) {
    const config = this.config.spriteAnimators.find(cfg => cfg.name === name);
    if (!config.options || config.options.autoPlay !== false) {
      animator.play();
    }
  }

  // Trigger auto sequences
  const autoSequences = this.config.autoSequences || [];
  for (const sequenceName of autoSequences) {
    this.triggerSequence(sequenceName);
  }
};

// Updated enter site handler
async function handleEnterSite() {
  console.log("User clicked Enter Site");

  // Hide splash screen
  document.getElementById('litmas-splash').style.display = 'none';

  // Start background music
  const audio = document.getElementById('litmas-music');
  if (audio) {
    try {
      await audio.play();
    } catch (e) {
      console.warn('User interaction failed to trigger audio:', e);
    }
  }

  // If scene isn't prepared yet, prepare it first
  if (!sceneBootstrap) {
    document.body.classList.add('canvas-mode');
    await prepareScene();
  }

  // Now start the actual animations
  if (sceneBootstrap) {
    sceneBootstrap.startSceneAnimations();
  }
}

// Initialize when DOM is ready - but only prepare, don't start
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    // Set up the enter site button handler
    const enterButtons = ['enter-site-naughty', 'enter-site-nice'];
    enterButtons.forEach(id => {
      const button = document.getElementById(id);
      if (button) {
        button.addEventListener('click', handleEnterSite);
      }
    });

    // Prepare scene but don't start animations
    document.body.classList.add('canvas-mode');
    prepareScene();
  });
} else {
  // Set up the enter site button handler
  const enterButtons = ['enter-site-naughty', 'enter-site-nice'];
  enterButtons.forEach(id => {
      const button = document.getElementById(id);
      if (button) {
        button.addEventListener('click', handleEnterSite);
      }
  });
  
  // Prepare scene but don't start animations
  document.body.classList.add('canvas-mode');
  prepareScene();
}

// Handle page unload
window.addEventListener('beforeunload', () => {
  if (sceneBootstrap) {
    sceneBootstrap.stop();
  }
});

// Export for module usage
export { SceneBootstrap };
