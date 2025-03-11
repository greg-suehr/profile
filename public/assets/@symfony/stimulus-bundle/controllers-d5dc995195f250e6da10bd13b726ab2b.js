import controller_0 from "../ux-turbo/turbo_controller.js";
import controller_1 from "../../controllers/hello_controller.js";
export const eagerControllers = {"symfony--ux-turbo--turbo-core": controller_0, "hello": controller_1};
export const lazyControllers = {"toggle-form": () => import("../../controllers/toggle_form_controller.js")};
export const isApplicationDebug = true;