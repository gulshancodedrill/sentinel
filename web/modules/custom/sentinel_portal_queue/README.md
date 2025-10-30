# Sentinel Portal Queue Module - Conversion Status

## Overview
This module manages the queue system for processing sentinel samples, including sending reports, handling invalid packs, and generating results.

## Drupal 7 Functionality
The D7 module provided:
1. Queue management UI at `/portal/admin/queue`
2. Custom queue implementation (SentinelPortalQueue class)
3. Cron-based queue processing
4. Email sending with multilingual templates (English, German, French, Italian)
5. PDF report generation and attachment
6. Queue item viewing, releasing, and deletion
7. Integration with Rules module for email sending
8. Views integration for queue data

## Conversion Complexity
This module is **significantly complex** and requires careful conversion due to:

### 1. Custom Queue Implementation
- D7 uses a custom `SentinelPortalQueue` class extending `SystemQueue`
- Custom database schema for queue items
- Needs conversion to Drupal 11 Queue API or custom implementation

### 2. Email Templates
- Extensive multilingual email templates (600+ lines of HTML)
- Country-specific logic (UK, DE, FR, IT)
- Sample type-specific logic (Vaillant vs standard)
- Pass/Fail specific messaging

### 3. External Dependencies
- Rules module integration (`rules_invoke_event`)
- PDF generation (`$sample_entity->GetPDF()`)
- Certificate generation (`sentinel_systemcheck_certificate_populate_results`)
- Extensive entity operations

### 4. Database Schema
Custom `sentinel_portal_queue` table with fields:
- item_id (serial primary key)
- name (queue name)
- pid (pack/sample ID)
- action (sendreport, invalid_pack, generate_results, invoke_send_results_hook)
- expire (timestamp)
- created (timestamp)
- failed (failure count)

## Recommended Conversion Approach

### Phase 1: Core Infrastructure
1. Create `.info.yml` file with dependencies
2. Implement database schema via `hook_schema()` in `.install` file
3. Create Queue Worker plugin for cron processing
4. Create basic routing for admin pages

### Phase 2: Queue Management UI
1. Convert admin form to FormBase
2. Create controller for queue listing
3. Implement view/release/delete forms using ConfirmFormBase
4. Add filtering and sorting functionality

### Phase 3: Queue Processing
1. Convert cron queue worker
2. Implement action processors:
   - `_sentinel_portal_queue_process_sendresults()`
   - `_sentinel_portal_queue_process_invalid_pack()`
   - `_sentinel_portal_queue_generate_results()`
   - `_sentinel_portal_queue_invoke_send_results_hook()`

### Phase 4: Email System
1. Convert `hook_mail()` implementation
2. Implement multilingual email templates
3. Convert email sending logic
4. Handle PDF attachments

### Phase 5: Integration
1. Replace Rules module calls with Event Dispatcher
2. Ensure entity integration works
3. Test queue item creation from other modules
4. Implement Views integration if needed

## Dependencies Required
```yaml
dependencies:
  - sentinel_portal_module
  - sentinel_portal_entities
  - file
  - user
  - system
```

## Key Functions to Convert

### Queue Management
- `sentinel_portal_queue_create_item()` - Create queue items
- `sentinel_portal_queue_run_action()` - Process queue items
- Custom queue class methods (inspect, view, release, delete)

### Email Processing
- `_sentinel_portal_queue_process_email()` - Main email processor
- `_sentinel_portal_queue_process_sendresults()` - Send results
- `_sentinel_portal_queue_process_invalid_pack()` - Handle invalid packs
- `_sentinel_portal_queue_generate_results()` - Generate results
- `_sentinel_portal_queue_invoke_send_results_hook()` - Hook invocation

### Helper Functions
- `_sentinel_portal_service_retrieve_by_pid()` - Load sample by PID
- `sentinel_portal_service_validate_dates()` - Date validation
- `sentinel_portal_service_format_packref()` - Pack reference formatting

## File Structure for D11

```
sentinel_portal_queue/
├── sentinel_portal_queue.info.yml
├── sentinel_portal_queue.routing.yml
├── sentinel_portal_queue.module
├── sentinel_portal_queue.install
├── config/
│   └── schema/
│       └── sentinel_portal_queue.schema.yml
├── src/
│   ├── Controller/
│   │   └── QueueAdminController.php
│   ├── Form/
│   │   ├── QueueAdminForm.php
│   │   ├── QueueReleaseForm.php
│   │   └── QueueDeleteForm.php
│   ├── Plugin/
│   │   ├── QueueWorker/
│   │   │   └── SentinelQueueWorker.php
│   │   └── Mail/
│   │       └── SentinelMailPlugin.php
│   └── Service/
│       ├── QueueService.php
│       └── EmailService.php
└── README.md
```

## Estimated Conversion Effort
- **Complexity**: High
- **Estimated Time**: 16-24 hours
- **Lines of Code**: ~1500-2000 lines
- **Risk**: Medium-High (due to external dependencies)

## Current Status
⚠️ **NOT CONVERTED** - This module requires significant development effort due to:
1. Custom queue implementation
2. Extensive email templating
3. Multiple external dependencies
4. Complex business logic

## Recommendations
1. **Priority Assessment**: Determine if queue functionality is critical for initial D11 launch
2. **Dependency Review**: Ensure all required modules (PDF generation, certificate generation) are available in D11
3. **Rules Replacement**: Plan migration from Rules module to Event Dispatcher/Symfony Events
4. **Email Service**: Consider using modern email service (e.g., Symfony Mailer)
5. **Queue Backend**: Evaluate if custom queue is needed or if Drupal 11's Queue API suffices
6. **Incremental Migration**: Consider migrating in phases, starting with core queue functionality

## Alternative Approaches
1. **Use Drupal 11 Queue API**: Leverage built-in queue system instead of custom implementation
2. **Advanced Queue Module**: Consider using contrib Advanced Queue module
3. **Email Templates**: Use Twig templates for emails instead of hardcoded HTML
4. **Batch API**: For bulk operations, consider using Batch API instead of queue

## Testing Requirements
Once converted, extensive testing needed for:
- Queue item creation and processing
- Email delivery (all languages and sample types)
- PDF attachment handling
- Cron execution
- UI operations (view, release, delete)
- Error handling and retry logic

## Author
Drupal 7 to Drupal 11 Conversion Analysis
Date: 2025-01-15



