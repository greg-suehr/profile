import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

import { Application } from "@hotwired/stimulus";

const app = Application.start();

// Import all controllers from assets/controllers
import ToggleFormController from "./controllers/toggle_form_controller";

app.register("toggle-form", ToggleFormController);
