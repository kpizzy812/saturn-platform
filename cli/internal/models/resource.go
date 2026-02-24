package models

// Resource represents any deployable resource
type Resource struct {
	ID                    int     `json:"-" table:"-"`
	UUID                  string  `json:"uuid"`
	Name                  string  `json:"name"`
	Type                  string  `json:"type"`
	Status                string  `json:"status"`
	GitRepository         *string `json:"git_repository,omitempty" table:"-"`
	GitBranch             *string `json:"git_branch,omitempty" table:"-"`
	BaseDirectory         *string `json:"base_directory,omitempty" table:"-"`
	BuildPack             *string `json:"build_pack,omitempty" table:"-"`
	DockerComposeLocation *string `json:"docker_compose_location,omitempty" table:"-"`
	MonorepoGroupID       *string `json:"monorepo_group_id,omitempty" table:"-"`
}

// ResourceStatus constants
const (
	ResourceStatusRunning = "running"
	ResourceStatusStopped = "stopped"
	ResourceStatusError   = "error"
)
