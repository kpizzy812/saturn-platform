package service

import (
	"context"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"gopkg.in/yaml.v3"

	"github.com/saturn-platform/saturn-cli/internal/api"
	"github.com/saturn-platform/saturn-cli/internal/models"
)

// SmartDeployService handles smart monorepo deployment logic
type SmartDeployService struct {
	resourceSvc *ResourceService
	deploySvc   *DeploymentService
}

// NewSmartDeployService creates a new smart deploy service
func NewSmartDeployService(client *api.Client) *SmartDeployService {
	return &SmartDeployService{
		resourceSvc: NewResourceService(client),
		deploySvc:   NewDeploymentService(client),
	}
}

// DeploymentService returns the underlying deployment service for wait operations
func (s *SmartDeployService) DeploymentService() *DeploymentService {
	return s.deploySvc
}

// --- Config operations ---

const configFileName = ".saturn.yml"

// LoadConfig reads and parses .saturn.yml from the given directory.
// Returns nil, nil if the file does not exist.
func LoadConfig(dir string) (*models.SmartConfig, error) {
	path := filepath.Join(dir, configFileName)

	data, err := os.ReadFile(path)
	if err != nil {
		if os.IsNotExist(err) {
			return nil, nil
		}
		return nil, fmt.Errorf("failed to read %s: %w", configFileName, err)
	}

	var cfg models.SmartConfig
	if err := yaml.Unmarshal(data, &cfg); err != nil {
		return nil, fmt.Errorf("failed to parse %s: %w", configFileName, err)
	}

	if cfg.Version != 1 {
		return nil, fmt.Errorf("unsupported %s version: %d (expected 1)", configFileName, cfg.Version)
	}

	return &cfg, nil
}

// WriteConfig serializes a SmartConfig to .saturn.yml in the given directory.
func WriteConfig(dir string, cfg *models.SmartConfig) error {
	path := filepath.Join(dir, configFileName)

	data, err := yaml.Marshal(cfg)
	if err != nil {
		return fmt.Errorf("failed to marshal config: %w", err)
	}

	if err := os.WriteFile(path, data, 0600); err != nil {
		return fmt.Errorf("failed to write %s: %w", configFileName, err)
	}

	return nil
}

// GenerateConfig builds a SmartConfig from a list of API resources.
func GenerateConfig(resources []models.Resource) *models.SmartConfig {
	cfg := &models.SmartConfig{
		Version:    1,
		BaseBranch: "main",
		Components: make(map[string]models.SmartComponent),
	}

	for _, r := range resources {
		if r.GitRepository == nil {
			continue
		}

		comp := models.SmartComponent{
			Resource: r.Name,
		}

		if r.BaseDirectory != nil && *r.BaseDirectory != "" && *r.BaseDirectory != "/" {
			dir := strings.TrimPrefix(*r.BaseDirectory, "/")
			dir = strings.TrimSuffix(dir, "/")
			comp.Path = dir + "/**"
		} else {
			comp.Path = "**"
		}

		// Use a sanitized resource name as key
		key := sanitizeKey(r.Name)
		cfg.Components[key] = comp
	}

	return cfg
}

// sanitizeKey converts a resource name into a safe YAML key
func sanitizeKey(name string) string {
	name = strings.ToLower(name)
	replacer := strings.NewReplacer(" ", "-", "_", "-", ".", "-")
	name = replacer.Replace(name)
	// Remove non-alphanumeric chars except hyphens
	var b strings.Builder
	for _, ch := range name {
		if (ch >= 'a' && ch <= 'z') || (ch >= '0' && ch <= '9') || ch == '-' {
			b.WriteRune(ch)
		}
	}
	result := b.String()
	// Trim leading/trailing hyphens
	return strings.Trim(result, "-")
}

// --- Git operations ---

// GetChangedFiles returns files changed between base branch and HEAD.
func GetChangedFiles(base string) ([]string, error) {
	// #nosec G204 - base is from config, not user input
	cmd := exec.Command("git", "diff", "--name-only", base+"..HEAD")
	out, err := cmd.Output()
	if err != nil {
		return nil, fmt.Errorf("git diff failed: %w", err)
	}

	var files []string
	for _, line := range strings.Split(strings.TrimSpace(string(out)), "\n") {
		line = strings.TrimSpace(line)
		if line != "" {
			files = append(files, line)
		}
	}
	return files, nil
}

// GetGitRemoteURL returns the origin remote URL for the current repo.
func GetGitRemoteURL() (string, error) {
	cmd := exec.Command("git", "remote", "get-url", "origin")
	out, err := cmd.Output()
	if err != nil {
		return "", fmt.Errorf("failed to get git remote URL: %w", err)
	}
	return strings.TrimSpace(string(out)), nil
}

// NormalizeGitURL normalizes SSH and HTTPS git URLs to "host/org/repo" format.
func NormalizeGitURL(rawURL string) string {
	url := strings.TrimSpace(rawURL)
	url = strings.TrimSuffix(url, ".git")

	// SSH format: git@host:org/repo
	if strings.HasPrefix(url, "git@") {
		url = strings.TrimPrefix(url, "git@")
		url = strings.Replace(url, ":", "/", 1)
		return url
	}

	// HTTPS format: https://host/org/repo
	for _, prefix := range []string{"https://", "http://"} {
		if strings.HasPrefix(url, prefix) {
			return strings.TrimPrefix(url, prefix)
		}
	}

	return url
}

// --- Matching ---

// GlobMatch checks if a file path matches a glob pattern.
// Supports ** for recursive directory matching, * for single segment wildcard.
func GlobMatch(pattern, path string) bool {
	return globMatch(strings.Split(pattern, "/"), strings.Split(path, "/"))
}

func globMatch(pattern, path []string) bool {
	for len(pattern) > 0 {
		seg := pattern[0]
		pattern = pattern[1:]

		if seg == "**" {
			// ** at the end matches everything
			if len(pattern) == 0 {
				return true
			}
			// Try matching the rest of pattern at every position in path
			for i := 0; i <= len(path); i++ {
				if globMatch(pattern, path[i:]) {
					return true
				}
			}
			return false
		}

		if len(path) == 0 {
			return false
		}

		if !segmentMatch(seg, path[0]) {
			return false
		}
		path = path[1:]
	}

	return len(path) == 0
}

// segmentMatch matches a single path segment against a pattern segment with * wildcards.
func segmentMatch(pattern, s string) bool {
	if pattern == "*" {
		return true
	}
	// Simple * wildcard within segment
	if strings.Contains(pattern, "*") {
		parts := strings.Split(pattern, "*")
		if len(parts) == 2 {
			return strings.HasPrefix(s, parts[0]) && strings.HasSuffix(s, parts[1])
		}
	}
	return pattern == s
}

// BuildDeployPlan matches changed files to components, resolves triggers, and looks up UUIDs.
func (s *SmartDeployService) BuildDeployPlan(ctx context.Context, files []string, cfg *models.SmartConfig) (*models.SmartDeployPlan, error) {
	// Fetch resources for UUID lookup
	resources, err := s.resourceSvc.List(ctx)
	if err != nil {
		return nil, fmt.Errorf("failed to list resources: %w", err)
	}

	nameToResource := make(map[string]models.Resource)
	for _, r := range resources {
		nameToResource[r.Name] = r
	}

	plan := &models.SmartDeployPlan{
		BaseBranch: cfg.BaseBranch,
		FilesTotal: len(files),
	}

	// Track which components have direct changes
	directMatches := make(map[string][]string) // component name -> matched files

	for name, comp := range cfg.Components {
		for _, file := range files {
			if GlobMatch(comp.Path, file) {
				directMatches[name] = append(directMatches[name], file)
			}
		}
	}

	// Build components from direct matches
	added := make(map[string]bool)

	for name, matchedFiles := range directMatches {
		comp := cfg.Components[name]
		dc := models.SmartDeployComponent{
			Name:         name,
			ResourceName: comp.Resource,
			FilesChanged: len(matchedFiles),
			Reason:       "direct",
			Files:        matchedFiles,
		}

		if r, ok := nameToResource[comp.Resource]; ok {
			dc.ResourceUUID = r.UUID
		}

		plan.Components = append(plan.Components, dc)
		added[name] = true
	}

	// Resolve triggers: if component X has triggers: [Y, Z], and X has direct changes,
	// then Y and Z should also be deployed
	for name := range directMatches {
		comp := cfg.Components[name]
		for _, triggerTarget := range comp.Triggers {
			if added[triggerTarget] {
				continue
			}

			targetComp, exists := cfg.Components[triggerTarget]
			if !exists {
				continue
			}

			dc := models.SmartDeployComponent{
				Name:         triggerTarget,
				ResourceName: targetComp.Resource,
				Reason:       "triggered",
				TriggerBy:    name,
			}

			if r, ok := nameToResource[targetComp.Resource]; ok {
				dc.ResourceUUID = r.UUID
			}

			plan.Components = append(plan.Components, dc)
			added[triggerTarget] = true
		}
	}

	return plan, nil
}

// AutoDetect fetches resources from the API and matches them by git remote URL.
func (s *SmartDeployService) AutoDetect(ctx context.Context) (*models.SmartConfig, error) {
	localURL, err := GetGitRemoteURL()
	if err != nil {
		return nil, err
	}

	localNorm := NormalizeGitURL(localURL)

	resources, err := s.resourceSvc.List(ctx)
	if err != nil {
		return nil, fmt.Errorf("failed to list resources: %w", err)
	}

	var matched []models.Resource
	for _, r := range resources {
		if r.GitRepository == nil {
			continue
		}
		if NormalizeGitURL(*r.GitRepository) == localNorm {
			matched = append(matched, r)
		}
	}

	if len(matched) == 0 {
		return nil, fmt.Errorf("no Saturn resources found matching git remote %s", localURL)
	}

	return GenerateConfig(matched), nil
}

// --- Execution ---

// ExecuteSmartDeploy deploys all components in the plan.
func (s *SmartDeployService) ExecuteSmartDeploy(ctx context.Context, plan *models.SmartDeployPlan, force bool) ([]models.SmartDeployResult, []string) {
	var results []models.SmartDeployResult
	var allDeploymentUUIDs []string

	for _, comp := range plan.Components {
		if comp.ResourceUUID == "" {
			results = append(results, models.SmartDeployResult{
				Name:         comp.Name,
				ResourceName: comp.ResourceName,
				Success:      false,
				Error:        "resource UUID not found",
			})
			continue
		}

		res, err := s.deploySvc.Deploy(ctx, comp.ResourceUUID, force)
		if err != nil {
			results = append(results, models.SmartDeployResult{
				Name:         comp.Name,
				ResourceName: comp.ResourceName,
				ResourceUUID: comp.ResourceUUID,
				Success:      false,
				Error:        err.Error(),
			})
			continue
		}

		var uuids []string
		message := ""
		if len(res.Deployments) > 0 {
			message = res.Deployments[0].Message
			for _, d := range res.Deployments {
				if d.DeploymentUUID != "" {
					uuids = append(uuids, d.DeploymentUUID)
				}
			}
		}

		allDeploymentUUIDs = append(allDeploymentUUIDs, uuids...)

		results = append(results, models.SmartDeployResult{
			Name:            comp.Name,
			ResourceName:    comp.ResourceName,
			ResourceUUID:    comp.ResourceUUID,
			Success:         true,
			Message:         message,
			DeploymentUUIDs: uuids,
		})
	}

	return results, allDeploymentUUIDs
}
