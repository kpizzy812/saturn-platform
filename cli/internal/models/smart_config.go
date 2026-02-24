package models

// SmartConfig represents the .saturn.yml configuration file
type SmartConfig struct {
	Version    int                       `yaml:"version" json:"version"`
	BaseBranch string                    `yaml:"base_branch" json:"base_branch"`
	Components map[string]SmartComponent `yaml:"components" json:"components"`
}

// SmartComponent defines a monorepo component mapping
type SmartComponent struct {
	Path     string   `yaml:"path" json:"path"`
	Resource string   `yaml:"resource,omitempty" json:"resource,omitempty"`
	Triggers []string `yaml:"triggers,omitempty" json:"triggers,omitempty"`
}

// SmartDeployPlan is the result of analyzing changed files against components
type SmartDeployPlan struct {
	BaseBranch string                 `json:"base_branch"`
	Components []SmartDeployComponent `json:"components"`
	FilesTotal int                    `json:"files_total"`
}

// SmartDeployComponent represents a single component matched for deployment
type SmartDeployComponent struct {
	Name         string   `json:"name"`
	ResourceName string   `json:"resource_name"`
	ResourceUUID string   `json:"resource_uuid"`
	FilesChanged int      `json:"files_changed"`
	Reason       string   `json:"reason"` // "direct" or "triggered"
	TriggerBy    string   `json:"trigger_by,omitempty"`
	Files        []string `json:"-"`
}

// SmartDeployResult holds the outcome of deploying a single component
type SmartDeployResult struct {
	Name            string   `json:"name"`
	ResourceName    string   `json:"resource_name"`
	ResourceUUID    string   `json:"resource_uuid"`
	Success         bool     `json:"success"`
	Message         string   `json:"message"`
	DeploymentUUIDs []string `json:"deployment_uuids,omitempty"`
	Error           string   `json:"error,omitempty"`
}
