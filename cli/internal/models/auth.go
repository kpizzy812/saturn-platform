package models

// DeviceAuthResponse is returned by POST /api/v1/cli/auth/init
type DeviceAuthResponse struct {
	Code            string `json:"code"`
	Secret          string `json:"secret"`
	VerificationURL string `json:"verification_url"`
	ExpiresIn       int    `json:"expires_in"`
}

// DeviceAuthStatus is returned by GET /api/v1/cli/auth/check
type DeviceAuthStatus struct {
	Status   string `json:"status"`
	Token    string `json:"token,omitempty"`
	TeamName string `json:"team_name,omitempty"`
	UserName string `json:"user_name,omitempty"`
}
