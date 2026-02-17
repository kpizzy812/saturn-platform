package service

import (
	"context"
	"encoding/json"
	"fmt"
	"sort"

	"github.com/saturn-platform/saturn-cli/internal/api"
	"github.com/saturn-platform/saturn-cli/internal/models"
)

// DeploymentService handles deployment-related operations
type DeploymentService struct {
	client *api.Client
}

// NewDeploymentService creates a new deployment service
func NewDeploymentService(client *api.Client) *DeploymentService {
	return &DeploymentService{
		client: client,
	}
}

// DeploymentInfo represents a single deployment in the deploy response
type DeploymentInfo struct {
	Message        string `json:"message"`
	ResourceUUID   string `json:"resource_uuid"`
	DeploymentUUID string `json:"deployment_uuid"`
}

// DeployResponse represents the response from a deploy operation
type DeployResponse struct {
	Deployments []DeploymentInfo `json:"deployments"`
}

// Deploy triggers a deployment for a resource
func (s *DeploymentService) Deploy(ctx context.Context, uuid string, force bool) (*DeployResponse, error) {
	endpoint := fmt.Sprintf("deploy?uuid=%s", uuid)
	if force {
		endpoint += "&force=true"
	}

	var response DeployResponse
	err := s.client.Get(ctx, endpoint, &response)
	if err != nil {
		return nil, fmt.Errorf("failed to deploy resource %s: %w", uuid, err)
	}
	return &response, nil
}

// List retrieves all deployments
func (s *DeploymentService) List(ctx context.Context) ([]models.Deployment, error) {
	var deployments []models.Deployment
	err := s.client.Get(ctx, "deployments", &deployments)
	if err != nil {
		return nil, fmt.Errorf("failed to list deployments: %w", err)
	}
	return deployments, nil
}

// Get retrieves a deployment by UUID
func (s *DeploymentService) Get(ctx context.Context, uuid string) (*models.Deployment, error) {
	var deployment models.Deployment
	err := s.client.Get(ctx, fmt.Sprintf("deployments/%s", uuid), &deployment)
	if err != nil {
		return nil, fmt.Errorf("failed to get deployment %s: %w", uuid, err)
	}
	return &deployment, nil
}

// CancelResponse represents the response from canceling a deployment
type CancelResponse struct {
	Message        string `json:"message"`
	DeploymentUUID string `json:"deployment_uuid"`
	Status         string `json:"status"`
}

// Cancel cancels an in-progress deployment
// Note: This endpoint will be available in a future version of Saturn
func (s *DeploymentService) Cancel(ctx context.Context, uuid string) (*CancelResponse, error) {
	var response CancelResponse
	err := s.client.Post(ctx, fmt.Sprintf("deployments/%s/cancel", uuid), nil, &response)
	if err != nil {
		return nil, fmt.Errorf("failed to cancel deployment %s: %w", uuid, err)
	}
	return &response, nil
}

// DeploymentsListResponse represents the response from listing deployments
type DeploymentsListResponse struct {
	Count       int                 `json:"count"`
	Deployments []models.Deployment `json:"deployments"`
}

// ListByApplication retrieves all deployments for a specific application
func (s *DeploymentService) ListByApplication(ctx context.Context, appUUID string) ([]models.Deployment, error) {
	return s.ListByApplicationWithPagination(ctx, appUUID, 0, 0)
}

// ListByApplicationWithPagination retrieves deployments with pagination support
func (s *DeploymentService) ListByApplicationWithPagination(ctx context.Context, appUUID string, skip, take int) ([]models.Deployment, error) {
	endpoint := fmt.Sprintf("deployments/applications/%s", appUUID)

	// Add pagination parameters if specified
	if skip > 0 || take > 0 {
		endpoint += "?"
		if skip > 0 {
			endpoint += fmt.Sprintf("skip=%d", skip)
		}
		if take > 0 {
			if skip > 0 {
				endpoint += "&"
			}
			endpoint += fmt.Sprintf("take=%d", take)
		}
	}

	var response DeploymentsListResponse
	err := s.client.Get(ctx, endpoint, &response)
	if err != nil {
		return nil, fmt.Errorf("failed to list deployments for application %s: %w", appUUID, err)
	}
	return response.Deployments, nil
}

// GetLogsByApplication retrieves deployment logs for a specific application
// This gets the latest deployment and returns its logs
func (s *DeploymentService) GetLogsByApplication(ctx context.Context, appUUID string, lines int) (string, error) {
	return s.GetLogsByApplicationWithOptions(ctx, appUUID, lines, false)
}

// GetLogsByApplicationWithOptions retrieves deployment logs for a specific application with formatting options
func (s *DeploymentService) GetLogsByApplicationWithOptions(ctx context.Context, appUUID string, take int, showHidden bool) (string, error) {
	return s.GetLogsByApplicationWithFormat(ctx, appUUID, take, showHidden, "table")
}

// GetLogsByApplicationWithFormat retrieves deployment logs with format control
func (s *DeploymentService) GetLogsByApplicationWithFormat(ctx context.Context, appUUID string, take int, showHidden bool, format string) (string, error) {
	// Get deployments with optional limit
	// If take is 0, get all deployments; otherwise get only the specified number
	var deployments []models.Deployment
	var err error

	if take > 0 {
		deployments, err = s.ListByApplicationWithPagination(ctx, appUUID, 0, take)
	} else {
		deployments, err = s.ListByApplication(ctx, appUUID)
	}

	if err != nil {
		return "", fmt.Errorf("failed to list deployments for application %s: %w", appUUID, err)
	}

	if len(deployments) == 0 {
		return "No deployments found for this application", nil
	}

	// Sort deployments by UpdatedAt in descending order to get the most recent first
	sort.Slice(deployments, func(i, j int) bool {
		// If UpdatedAt is available, use it
		if deployments[i].UpdatedAt != nil && deployments[j].UpdatedAt != nil {
			return *deployments[i].UpdatedAt > *deployments[j].UpdatedAt
		}
		// Fall back to CreatedAt if UpdatedAt is not available
		if deployments[i].CreatedAt != nil && deployments[j].CreatedAt != nil {
			return *deployments[i].CreatedAt > *deployments[j].CreatedAt
		}
		// Fall back to ID if timestamps are not available (higher ID = more recent)
		return deployments[i].ID > deployments[j].ID
	})

	// Get the latest deployment (first after sorting)
	latestDeployment := deployments[0]

	// Get logs for this deployment only
	return s.GetLogsByDeploymentWithFormat(ctx, latestDeployment.UUID, showHidden, format)
}

// GetRollbackEvents retrieves rollback events for an application
func (s *DeploymentService) GetRollbackEvents(ctx context.Context, appUUID string, take int) ([]models.RollbackEvent, error) {
	endpoint := fmt.Sprintf("applications/%s/rollback-events", appUUID)
	if take > 0 {
		endpoint += fmt.Sprintf("?take=%d", take)
	}

	var events []models.RollbackEvent
	err := s.client.Get(ctx, endpoint, &events)
	if err != nil {
		return nil, fmt.Errorf("failed to get rollback events for application %s: %w", appUUID, err)
	}
	return events, nil
}

// ExecuteRollback triggers a rollback to a specific deployment
func (s *DeploymentService) ExecuteRollback(ctx context.Context, appUUID, deploymentUUID string) (*models.RollbackResponse, error) {
	var response models.RollbackResponse
	err := s.client.Post(ctx, fmt.Sprintf("applications/%s/rollback/%s", appUUID, deploymentUUID), nil, &response)
	if err != nil {
		return nil, fmt.Errorf("failed to execute rollback for application %s to deployment %s: %w", appUUID, deploymentUUID, err)
	}
	return &response, nil
}

// DeployByTag triggers a deployment by tag name
func (s *DeploymentService) DeployByTag(ctx context.Context, tag string, force bool) (*DeployResponse, error) {
	endpoint := fmt.Sprintf("deploy?tag=%s", tag)
	if force {
		endpoint += "&force=true"
	}

	var response DeployResponse
	err := s.client.Get(ctx, endpoint, &response)
	if err != nil {
		return nil, fmt.Errorf("failed to deploy by tag %s: %w", tag, err)
	}
	return &response, nil
}

// DeployByPR triggers a PR preview deployment
func (s *DeploymentService) DeployByPR(ctx context.Context, uuid string, prID int, force bool) (*DeployResponse, error) {
	endpoint := fmt.Sprintf("deploy?uuid=%s&pr=%d", uuid, prID)
	if force {
		endpoint += "&force=true"
	}

	var response DeployResponse
	err := s.client.Get(ctx, endpoint, &response)
	if err != nil {
		return nil, fmt.Errorf("failed to deploy PR #%d for application %s: %w", prID, uuid, err)
	}
	return &response, nil
}

// GetDeploymentLogs retrieves logs for a specific deployment via dedicated endpoint
func (s *DeploymentService) GetDeploymentLogs(ctx context.Context, deploymentUUID string) (*models.DeploymentLogsResponse, error) {
	var response models.DeploymentLogsResponse
	err := s.client.Get(ctx, fmt.Sprintf("deployments/%s/logs", deploymentUUID), &response)
	if err != nil {
		return nil, fmt.Errorf("failed to get logs for deployment %s: %w", deploymentUUID, err)
	}
	return &response, nil
}

// GetLogsByDeployment retrieves logs for a specific deployment by UUID
func (s *DeploymentService) GetLogsByDeployment(ctx context.Context, deploymentUUID string) (string, error) {
	return s.GetLogsByDeploymentWithOptions(ctx, deploymentUUID, false)
}

// GetLogsByDeploymentWithOptions retrieves logs for a specific deployment by UUID with formatting options
func (s *DeploymentService) GetLogsByDeploymentWithOptions(ctx context.Context, deploymentUUID string, showHidden bool) (string, error) {
	return s.GetLogsByDeploymentWithFormat(ctx, deploymentUUID, showHidden, "table")
}

// GetLogsByDeploymentWithFormat retrieves logs with format control (json/pretty/table)
func (s *DeploymentService) GetLogsByDeploymentWithFormat(ctx context.Context, deploymentUUID string, showHidden bool, format string) (string, error) {
	// Get the full deployment details which includes logs
	deployment, err := s.Get(ctx, deploymentUUID)
	if err != nil {
		return "", fmt.Errorf("failed to get deployment details: %w", err)
	}

	if deployment.Logs == nil || *deployment.Logs == "" {
		return fmt.Sprintf("No logs available for deployment %s (Status: %s)", deployment.UUID, deployment.Status), nil
	}

	// For JSON/pretty format, filter hidden logs if needed
	if format == "json" || format == "pretty" {
		logsJSON := *deployment.Logs

		// Filter out hidden logs unless showHidden is true
		if !showHidden {
			var logEntries []models.LogEntry
			if err := json.Unmarshal([]byte(logsJSON), &logEntries); err == nil {
				// Filter out hidden entries
				var filtered []models.LogEntry
				for _, entry := range logEntries {
					if !entry.Hidden {
						filtered = append(filtered, entry)
					}
				}

				// Re-marshal the filtered logs
				filteredBytes, err := json.Marshal(filtered)
				if err == nil {
					logsJSON = string(filteredBytes)
				}
			}
		}

		// For JSON format, return the filtered logs
		if format == "json" {
			return logsJSON, nil
		}

		// For pretty format, pretty-print the filtered JSON
		var prettyJSON interface{}
		// Try to unmarshal and marshal with indent - if either fails, return raw JSON
		if json.Unmarshal([]byte(logsJSON), &prettyJSON) == nil {
			if prettyBytes, err := json.MarshalIndent(prettyJSON, "", "  "); err == nil {
				return string(prettyBytes), nil
			}
		}
		return logsJSON, nil
	}

	// For table/text format, parse and format the logs
	// ParseAndFormatLogs will return original if parsing fails
	formattedLogs, _ := models.ParseAndFormatLogs(*deployment.Logs, showHidden)
	return formattedLogs, nil
}
