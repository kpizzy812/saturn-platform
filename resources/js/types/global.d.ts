/**
 * Global type declarations for window object extensions
 */

interface ProjectCanvasControls {
    __projectCanvasZoomIn?: () => void;
    __projectCanvasZoomOut?: () => void;
    __projectCanvasFitView?: () => void;
}

declare global {
    interface Window extends ProjectCanvasControls {}
}

export {};
