#!/bin/bash
# Check if test file exists for created/modified source file
# Returns reminder message if test is missing

FILE_PATH="$CLAUDE_FILE_PATHS"

# Skip if not a relevant file
if [[ ! "$FILE_PATH" =~ \.(php|tsx?)$ ]]; then
    exit 0
fi

# Skip test files themselves
if [[ "$FILE_PATH" =~ (Test\.php|\.test\.tsx?|\.spec\.tsx?)$ ]]; then
    exit 0
fi

# Skip migrations, configs, views
if [[ "$FILE_PATH" =~ (migrations/|config/|views/|resources/views/) ]]; then
    exit 0
fi

# Check PHP files in app/Actions, app/Services, app/Jobs
if [[ "$FILE_PATH" =~ ^app/(Actions|Services|Jobs)/.+\.php$ ]]; then
    # Extract class name
    CLASS_NAME=$(basename "$FILE_PATH" .php)
    TEST_PATH="tests/Unit/${FILE_PATH#app/}"
    TEST_PATH="${TEST_PATH%.php}Test.php"

    if [[ ! -f "$TEST_PATH" ]]; then
        echo "REMINDER: Test file missing for $FILE_PATH"
        echo "Expected: $TEST_PATH"
        echo "Use auto-test-generator skill to create tests"
    fi
fi

# Check React components
if [[ "$FILE_PATH" =~ ^resources/js/components/.+\.tsx$ ]]; then
    COMPONENT_NAME=$(basename "$FILE_PATH" .tsx)
    # Skip index files
    if [[ "$COMPONENT_NAME" != "index" ]]; then
        COMPONENT_DIR=$(dirname "$FILE_PATH")
        TEST_PATH="${COMPONENT_DIR}/__tests__/${COMPONENT_NAME}.test.tsx"

        if [[ ! -f "$TEST_PATH" ]]; then
            echo "REMINDER: Test file missing for $FILE_PATH"
            echo "Consider creating: $TEST_PATH"
        fi
    fi
fi

exit 0
