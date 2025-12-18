#!/bin/bash

# Batch import script for addresses using drush commands
# Enqueues records, processes all, then repeats
#
# Usage: ./batch_import_addresses_drush.sh [enqueue_size] [start_row]
#   enqueue_size: Number of items to enqueue per batch (default: 2000)
#   start_row: Row number to start from (default: 0, skips header)

BATCH_SIZE=${1:-2000}
START_ROW=${2:-0}
CSV_PATH="default"

# Get script directory and resolve paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SENTINEL_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CSV_FILE="$SENTINEL_ROOT/web/modules/custom/sentinel_data_import/csv/address_entities_d7.csv"

# Change to sentinel root directory for drush commands
cd "$SENTINEL_ROOT" || {
    echo "ERROR: Cannot change to directory: $SENTINEL_ROOT"
    exit 1
}

if [ ! -f "$CSV_FILE" ]; then
    echo "ERROR: CSV file not found: $CSV_FILE"
    echo "Please check the path or specify a custom CSV file"
    exit 1
fi

TOTAL_ROWS=$(tail -n +2 "$CSV_FILE" | wc -l | tr -d ' ')
CURRENT_ROW=$START_ROW
BATCH_NUMBER=0
TOTAL_PROCESSED=0
START_TIME=$(date +%s)

echo "========================================"
echo "Batch Address Import using Drush"
echo "========================================"
echo "CSV File: $CSV_FILE"
echo "Total rows: $TOTAL_ROWS"
echo "Enqueue size per batch: $BATCH_SIZE"
echo "Starting from row: $START_ROW"
echo "========================================"
echo ""

# Function to process a batch
process_batch() {
    local start=$1
    local limit=$2
    local batch_num=$3
    
    echo "Processing batch #$batch_num (rows $start to $((start + limit - 1)))..."
    
    # Enqueue batch
    echo "  Enqueuing batch..."
    # Try using /opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php first, fallback to php vendor/bin/drush
    if [ -x "/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php" ]; then
        ENQUEUE_OUTPUT=$(/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php sentinel-data-import:enqueue-addresses "$CSV_PATH" --start=$start --limit=$limit 2>&1)
        ENQUEUE_EXIT_CODE=$?
    else
        ENQUEUE_OUTPUT=$(php vendor/bin/drush sentinel-data-import:enqueue-addresses "$CSV_PATH" --start=$start --limit=$limit 2>&1)
        ENQUEUE_EXIT_CODE=$?
        # Filter out the drush wrapper script output and show only relevant messages
        ENQUEUE_OUTPUT=$(echo "$ENQUEUE_OUTPUT" | grep -v "^#" | grep -v "selfArg=" | grep -v "self=" | grep -v "dir=" | grep -v "export COMPOSER" | grep -v "bashSource=" | grep -v "source.*drush" | grep -v '"\${dir}/drush"')
    fi
    
    # Extract and display the result message
    ENQUEUE_RESULT=$(echo "$ENQUEUE_OUTPUT" | grep -E "\[(success|error|warning)\]|Processed|Enqueued|Skipped|ERROR" || echo "$ENQUEUE_OUTPUT" | tail -3)
    
    if [ -n "$ENQUEUE_RESULT" ]; then
        echo "$ENQUEUE_RESULT" | while IFS= read -r line; do
            echo "  $line"
        done
    fi
    
    if [ $ENQUEUE_EXIT_CODE -ne 0 ]; then
        echo "  ERROR: Failed to enqueue batch (exit code: $ENQUEUE_EXIT_CODE)"
        return 1
    fi
    
    # Get queue size before processing
    if [ -x "/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php" ]; then
        QUEUE_SIZE_BEFORE=$(/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php queue:list 2>/dev/null | grep "sentinel_data_import.address" | awk '{print $2}' | head -1 || echo "0")
    else
        QUEUE_SIZE_BEFORE=$(php vendor/bin/drush queue:list 2>/dev/null | grep "sentinel_data_import.address" | awk '{print $2}' | head -1 || echo "0")
    fi
    QUEUE_SIZE_BEFORE=$(echo "$QUEUE_SIZE_BEFORE" | tr -d '[:space:]' | grep -o '[0-9]*' || echo "0")
    
    echo "  Queue size before processing: $QUEUE_SIZE_BEFORE items"
    
    # Process queue until completely empty
    echo "  Processing queue until empty..."
    MAX_ITERATIONS=500
    ITERATION=0
    
    while [ $ITERATION -lt $MAX_ITERATIONS ]; do
        if [ -x "/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php" ]; then
            QUEUE_SIZE=$(/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php queue:list 2>/dev/null | grep "sentinel_data_import.address" | awk '{print $2}' | head -1 || echo "0")
        else
            QUEUE_SIZE=$(php vendor/bin/drush queue:list 2>/dev/null | grep "sentinel_data_import.address" | awk '{print $2}' | head -1 || echo "0")
        fi
        QUEUE_SIZE=$(echo "$QUEUE_SIZE" | tr -d '[:space:]' | grep -o '[0-9]*' || echo "0")
        
        if [ "$QUEUE_SIZE" = "0" ] || [ -z "$QUEUE_SIZE" ]; then
            echo "  Queue is empty. Batch complete."
            break
        fi
        
        echo "    Processing queue items... (remaining: $QUEUE_SIZE)"
        if [ -x "/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php" ]; then
            /opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php queue:run sentinel_data_import.address --time-limit=60 2>&1 | grep -E "(Processed|success|error|warning)" || true
        else
            php vendor/bin/drush queue:run sentinel_data_import.address --time-limit=60 2>&1 | grep -v "^#" | grep -v "selfArg=" | grep -E "(Processed|success|error|warning)" || true
        fi
        
        ITERATION=$((ITERATION + 1))
        
        # Small delay
        sleep 0.3
    done
    
    if [ $ITERATION -ge $MAX_ITERATIONS ]; then
        echo "  WARNING: Reached max iterations. Some items may remain."
    fi
    
    if [ -x "/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php" ]; then
        QUEUE_SIZE_AFTER=$(/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php queue:list 2>/dev/null | grep "sentinel_data_import.address" | awk '{print $2}' | head -1 || echo "0")
    else
        QUEUE_SIZE_AFTER=$(php vendor/bin/drush queue:list 2>/dev/null | grep "sentinel_data_import.address" | awk '{print $2}' | head -1 || echo "0")
    fi
    QUEUE_SIZE_AFTER=$(echo "$QUEUE_SIZE_AFTER" | tr -d '[:space:]' | grep -o '[0-9]*' || echo "0")
    PROCESSED_IN_BATCH=$((QUEUE_SIZE_BEFORE - QUEUE_SIZE_AFTER))
    
    echo "  Processed: $PROCESSED_IN_BATCH items in this batch"
    echo ""
    
    return 0
}

# Main processing loop
while [ $CURRENT_ROW -lt $TOTAL_ROWS ]; do
    BATCH_NUMBER=$((BATCH_NUMBER + 1))
    REMAINING=$((TOTAL_ROWS - CURRENT_ROW))
    
    if [ $REMAINING -lt $BATCH_SIZE ]; then
        # Last batch - process remaining items
        ACTUAL_BATCH_SIZE=$REMAINING
    else
        ACTUAL_BATCH_SIZE=$BATCH_SIZE
    fi
    
    # Process this batch
    process_batch $CURRENT_ROW $ACTUAL_BATCH_SIZE $BATCH_NUMBER
    
    if [ $? -ne 0 ]; then
        echo "ERROR: Failed to process batch #$BATCH_NUMBER"
        echo "Stopping at row $CURRENT_ROW"
        exit 1
    fi
    
    TOTAL_PROCESSED=$((TOTAL_PROCESSED + ACTUAL_BATCH_SIZE))
    CURRENT_ROW=$((CURRENT_ROW + ACTUAL_BATCH_SIZE))
    
    # Show progress
    ELAPSED=$(($(date +%s) - START_TIME))
    PERCENTAGE=0
    if [ $TOTAL_ROWS -gt 0 ]; then
        PERCENTAGE=$((TOTAL_PROCESSED * 100 / TOTAL_ROWS))
    fi
    RATE=0
    if [ $ELAPSED -gt 0 ]; then
        RATE=$((TOTAL_PROCESSED / ELAPSED))
    fi
    
    echo "Progress: $TOTAL_PROCESSED / $TOTAL_ROWS rows ($PERCENTAGE%) - Rate: $RATE rows/sec"
    echo ""
    
    # Small delay between batches
    sleep 1
done

# Final summary
ELAPSED=$(($(date +%s) - START_TIME))
RATE=0
if [ $ELAPSED -gt 0 ]; then
    RATE=$((TOTAL_PROCESSED / ELAPSED))
fi

echo "========================================"
echo "Address Import Complete"
echo "========================================"
echo "Total rows processed: $TOTAL_PROCESSED"
echo "Batches processed: $BATCH_NUMBER"
echo "Time elapsed: $ELAPSED seconds"
echo "Processing rate: $RATE rows/sec"
echo "========================================"

# Check if queue is empty
if [ -x "/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php" ]; then
    FINAL_QUEUE_SIZE=$(/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php queue:list 2>/dev/null | grep "sentinel_data_import.address" | awk '{print $2}' | head -1 || echo "0")
    DRUSH_CMD="/opt/cpanel/ea-php83/root/usr/bin/php vendor/drush/drush/drush.php"
else
    FINAL_QUEUE_SIZE=$(php vendor/bin/drush queue:list 2>/dev/null | grep "sentinel_data_import.address" | awk '{print $2}' | head -1 || echo "0")
    DRUSH_CMD="php vendor/bin/drush"
fi
FINAL_QUEUE_SIZE=$(echo "$FINAL_QUEUE_SIZE" | tr -d '[:space:]' | grep -o '[0-9]*' || echo "0")
if [ "$FINAL_QUEUE_SIZE" != "0" ] && [ -n "$FINAL_QUEUE_SIZE" ]; then
    echo ""
    echo "WARNING: There are still $FINAL_QUEUE_SIZE items in the queue."
    echo "Run '$DRUSH_CMD queue:run sentinel_data_import.address' to process them."
fi

echo ""
echo "Done."

