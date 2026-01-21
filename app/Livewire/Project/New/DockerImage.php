<?php

namespace App\Livewire\Project\New;

use Livewire\Component;

class DockerImage extends Component
{
    public string $imageName = '';

    public string $imageTag = '';

    public string $imageSha256 = '';

    /**
     * Called when image name is updated - auto-parses tags and digests.
     */
    public function updatedImageName(): void
    {
        // Don't auto-parse if user has already filled in tag or sha256
        if (! empty($this->imageTag) || ! empty($this->imageSha256)) {
            return;
        }

        // Don't parse if no special characters (just a plain image name)
        if (! str_contains($this->imageName, ':') && ! str_contains($this->imageName, '@')) {
            return;
        }

        $this->parseImageReference();
    }

    /**
     * Parse docker image reference and extract components.
     *
     * Handles formats like:
     * - nginx:stable-alpine3.21-perl (image with tag)
     * - nginx@sha256:abc123... (image with digest)
     * - nginx:tag@sha256:abc123... (image with tag and digest)
     * - registry.io:5000/myapp:v1.2.3 (registry with port and tag)
     * - ghcr.io/user/app@sha256:abc123... (ghcr with digest)
     */
    protected function parseImageReference(): void
    {
        $name = $this->imageName;

        // Check for sha256 digest first
        if (str_contains($name, '@sha256:')) {
            $parts = explode('@sha256:', $name, 2);
            $imageWithPossibleTag = $parts[0];
            $this->imageSha256 = $parts[1];

            // If there's also a tag, keep it with the image name
            // Docker uses the digest for pulling but tag can stay for identification
            $this->imageName = $imageWithPossibleTag;

            return;
        }

        // Check for tag (but not registry port)
        // Registry port format: registry.io:5000/image
        // Tag format: image:tag
        $lastSlashPos = strrpos($name, '/');
        $colonPos = strrpos($name, ':');

        // If colon is before slash (or no slash), it's likely a registry port, not a tag
        if ($colonPos !== false && ($lastSlashPos === false || $colonPos > $lastSlashPos)) {
            $parts = explode(':', $name);
            $tag = array_pop($parts);
            $imageName = implode(':', $parts);

            // Verify this looks like a tag (not a port number followed by a path)
            if (! is_numeric($tag) || ! str_contains($tag, '/')) {
                $this->imageName = $imageName;
                $this->imageTag = $tag;
            }
        }
    }

    public function render()
    {
        return view('livewire.project.new.docker-image');
    }
}
