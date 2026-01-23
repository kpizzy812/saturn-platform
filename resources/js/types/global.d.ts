/**
 * Global type declarations for window object extensions
 */

interface ProjectCanvasControls {
    __projectCanvasZoomIn?: () => void;
    __projectCanvasZoomOut?: () => void;
    __projectCanvasFitView?: () => void;
}

/**
 * Safari/WebKit Audio API compatibility
 */
interface WebkitWindow {
    webkitAudioContext?: typeof AudioContext;
}

declare global {
    interface Window extends ProjectCanvasControls, WebkitWindow {}
}

export {};
