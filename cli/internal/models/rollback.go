package models

// RollbackEvent represents a rollback event for an application
type RollbackEvent struct {
	ID              int            `json:"id"`
	ApplicationID   int            `json:"application_id" table:"-"`
	TriggerReason   string         `json:"trigger_reason"`
	TriggerType     string         `json:"trigger_type"`
	Status          string         `json:"status"`
	FromCommit      *string        `json:"from_commit,omitempty"`
	ToCommit        *string        `json:"to_commit,omitempty"`
	User            *RollbackUser  `json:"user,omitempty" table:"-"`
	CreatedAt       *string        `json:"created_at,omitempty" table:"-"`
	UpdatedAt       *string        `json:"updated_at,omitempty" table:"-"`
}

// RollbackUser represents the user who triggered a rollback
type RollbackUser struct {
	ID    int    `json:"id"`
	Name  string `json:"name"`
	Email string `json:"email"`
}

// RollbackResponse represents the response from executing a rollback
type RollbackResponse struct {
	Message         string `json:"message"`
	DeploymentUUID  string `json:"deployment_uuid,omitempty"`
	RollbackEventID *int   `json:"rollback_event_id,omitempty"`
}
