import * as PIXI from 'pixi.js';
import { GlitchFilter } from '@pixi/filter-glitch';
import gsap from 'gsap';
import { Howl } from 'howler';

let app, greg, wrench, glitchFilter, humSound;
let blueRift, orangeRift;

export function startPrologueAnimation(canvasId = 'screen') {
  // 1. Create PIXI app
  app = new PIXI.Application({
    width: 800,
    height: 400,
    backgroundColor: 0x000000,
    view: document.getElementById(canvasId)
  });

  // 2. Load assets
  app.loader
    .add('greg', '/assets/images/greg.png')
    .add('wrench', '/assets/images/wrench.png')
    .add('blueRift', '/assets/images/blue-rift.png')
    .add('orangeRift', '/assets/images/orange-rift.png')
    .load(setup);
}

function setup() {
  // Create Greg sprite
  greg = new PIXI.Sprite(app.loader.resources.greg.texture);
  greg.anchor.set(0.5);
  greg.x = app.renderer.width / 2;
  greg.y = app.renderer.height / 2;
  app.stage.addChild(greg);

  // Create wrench sprite
  wrench = new PIXI.Sprite(app.loader.resources.wrench.texture);
  wrench.anchor.set(0.5, 0);
  wrench.x = greg.x + 50;
  wrench.y = greg.y + 20;
  app.stage.addChild(wrench);

  // Create glitch filter (initially off)
  glitchFilter = new GlitchFilter({ slices: 0, offset: 0, fillMode: 1 });
  greg.filters = [];

  // Prepare hum sound
  humSound = new Howl({
    src: ['/assets/audio/hum.mp3'],
    loop: true,
    volume: 0
  });

  // Create rifts (hidden until later)
  blueRift = new PIXI.Sprite(app.loader.resources.blueRift.texture);
  orangeRift = new PIXI.Sprite(app.loader.resources.orangeRift.texture);
  [blueRift, orangeRift].forEach((rift, i) => {
    rift.anchor.set(0.5);
    rift.x = app.renderer.width * (i ? 0.75 : 0.25);
    rift.y = app.renderer.height / 2;
    rift.alpha = 0;
    rift.scale.set(1);
    app.stage.addChild(rift);
  });

  // Kick off the timeline
  buildAndPlayTimeline();
}

function buildAndPlayTimeline() {
  const tl = gsap.timeline();

  // 1. Start glitch + hum fade-in
  tl.to({}, {
    duration: 0.2,
    onStart: () => {
      greg.filters = [glitchFilter];
      humSound.play();
    }
  });
  tl.to(glitchFilter, {
    slices: 15,
    offset: 100,
    duration: 1.5,
    ease: 'power2.inOut'
  }, '<');
  tl.to(humSound, {
    // Howler sound fades must be done imperatively
    onStart: () => humSound.fade(0, 0.3, 1500)
  }, '<');

  // 2. Greg “!” shake
  tl.to(greg, {
    x: '+=5',
    yoyo: true,
    repeat: 5,
    duration: 0.05
  }, '>-0.5');

  // 3. Drop wrench
  tl.to(wrench, {
    y: app.renderer.height - 20,
    rotation: 0.5,
    duration: 0.8,
    ease: 'bounce.out'
  }, '>-0.3');

  // 4. Pull Greg into rift + intensify glitch
  tl.to(glitchFilter, {
    offset: 300,
    duration: 0.6
  }, '>-0.1');
  tl.to(greg, {
    scale: 0.2,
    alpha: 0,
    duration: 0.7
  }, '<');
  tl.to({}, {
    duration: 0.5,
    onComplete: () => humSound.fade(0.3, 0, 700)
  }, '<+0.2');

  // 5. Clear filters + stop hum + auto-navigate
  tl.to(glitchFilter, {
    slices: 0,
    offset: 0,
    duration: 0.3
  }, '>-0.2');
  tl.to({}, {
    duration: 0,
    onComplete: () => {
      greg.filters = [];
      humSound.stop();
      // advance to next page
      window.location.href = '/story/prologue8';
    }
  });

  // Play timeline
  tl.play();
}

// Call this after user choice to reveal and pulse rifts
export function revealRifts() {
  gsap.to([blueRift, orangeRift], {
    alpha: 1,
    duration: 0.8,
    ease: 'power1.inOut',
    onComplete() {
      gsap.to(blueRift, { scale: 1.05, repeat: -1, yoyo: true, duration: 1.2 });
      gsap.to(orangeRift, { scale: 1.05, repeat: -1, yoyo: true, duration: 1.2 });
    }
  });
}