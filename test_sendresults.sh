#!/bin/bash
# Test script to simulate sendresults workflow as www-data user

SAMPLE_ID=${1:-748116}

echo "================================"
echo "Testing sendresults workflow for sample $SAMPLE_ID"
echo "================================"

cd /var/www/html/sentinel11/sentinel

echo ""
echo "Step 1: Reset sample fields..."
sudo -u www-data ./vendor/bin/drush sqlq "UPDATE sentinel_sample SET date_reported = NOW(), fileid = NULL, filename = NULL WHERE pid = $SAMPLE_ID"

echo ""
echo "Step 2: Queue sendresults..."
sudo -u www-data ./vendor/bin/drush php:eval "\$item = new stdClass(); \$item->pid = $SAMPLE_ID; sentinel_portal_queue_create_item(\$item, 'sendreport'); echo 'Queued sample $SAMPLE_ID\n';"

echo ""
echo "Step 3: Process queue..."
sudo -u www-data ./vendor/bin/drush queue:run sentinel_queue

echo ""
echo "Step 4: Check database..."
sudo -u www-data ./vendor/bin/drush sqlq "SELECT pid, pack_reference_number, fileid, filename FROM sentinel_sample WHERE pid = $SAMPLE_ID"

echo ""
echo "Step 5: Check logs..."
sudo -u www-data ./vendor/bin/drush ws --type=sentinel_portal_queue --count=3

echo ""
echo "================================"
echo "✓ Test complete!"
echo "Check gulshan.codedrill@gmail.com for email with PDF attachment"
echo "================================"

