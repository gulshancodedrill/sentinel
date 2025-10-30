# Sentinel Stats Module

This module creates ECK entities when a sample is calculated. It includes:

## Features

1. **ECK Entity Type**: `sentinel_stat` entity with `sentinel_stat` bundle
2. **Queue Worker**: Processes stats generation queue items
3. **Cron Integration**: Automatically queues samples that need stats entities created

## Conversion Notes (Drupal 7 to Drupal 11)

This module was converted from a Drupal 7 Features module.

### Module Structure

- `sentinel_stats.info.yml` - Module definition
- `sentinel_stats.module` - Main module file with ECK hooks and cron
- `src/Plugin/QueueWorker/SentinelStatsQueueWorker.php` - Queue worker plugin

### Queue Worker

The module implements a queue worker that processes samples and calls `sentinel_systemcheck_certificate_populate_results()` to create stat entities.

### Cron

`hook_cron()` collects all samples that need stat entities created (samples reported after 2015-12-30 that don't have stat entities yet) and adds them to the queue.

## Fields Required

The following fields need to be manually created on the `sentinel_stat` ECK entity type, `sentinel_stat` bundle:
- `field_stat_element_name` (text, max 255)
- `field_stat_individual_comment` (text long ch)
- `field_stat_pack_reference_id` (entity_reference to sentinel_sample)
- `field_stat_recommendation` (text ⊞ long)
- `field_stat_result` (entity_reference to taxonomy_term with vid = 2)

## Views

Views for this module should be created manually:
- `delete_sentinel_stats` - View for deleting stats (was in Features)
- `sample_export_stats` - View for exporting sample stats (was in Features)


