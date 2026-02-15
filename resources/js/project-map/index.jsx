import React from 'react';
import { createRoot } from 'react-dom/client';
import ProjectMap from './components/ProjectMap';

// Initialize Project Map when the container is available
function initProjectMap() {
    const container = document.getElementById('project-map-container');
    if (container) {
        const dataElement = document.getElementById('project-map-data');
        let initialData = { nodes: [], edges: [] };

        if (dataElement) {
            try {
                initialData = JSON.parse(dataElement.textContent);
            } catch (e) {
                console.error('[Saturn] Failed to parse project map data:', e);
            }
        }

        const root = createRoot(container);
        root.render(
            <React.StrictMode>
                <ProjectMap initialData={initialData} />
            </React.StrictMode>
        );
    }
}

// Listen for Livewire navigation and Alpine init
['livewire:navigated', 'alpine:init', 'DOMContentLoaded'].forEach((event) => {
    document.addEventListener(event, initProjectMap);
});

// Export for potential external use
window.initProjectMap = initProjectMap;
