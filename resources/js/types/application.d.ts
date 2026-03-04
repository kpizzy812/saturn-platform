// Application Settings shape (stored in application_settings table)
export interface ApplicationSettings {
    // Rollback settings
    auto_rollback_enabled: boolean;
    rollback_validation_seconds: number;
    rollback_max_restarts: number;
    rollback_on_health_check_fail: boolean;
    rollback_on_crash_loop: boolean;
    docker_images_to_keep: number;
    wait_for_ci: boolean;
    // Canary deployment settings
    canary_enabled: boolean;
    canary_steps: number[] | null;
    canary_step_minutes: number;
    canary_auto_promote: boolean;
    canary_error_rate_threshold: number;
}
