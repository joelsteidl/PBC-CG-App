# Drush Command: group-attendance-missing

## Overview
The `group-attendance-missing` command identifies missing `group_attendance_record` nodes for active groups by checking their scheduled meeting days against a date range.

## Command Name
```
pbc-automation:group-attendance-missing
```

## Alias
```
group-attendance-missing
```

## Usage

### Basic Usage
Check for missing attendance records from a start date to today:
```bash
drush group-attendance-missing 2025-01-01
ddev drush group-attendance-missing 2025-01-01
```

### Required Arguments
- **`start_date`** (required): The start date in Y-m-d format (e.g., 2025-01-01)

### How It Works
1. **Retrieves all active groups** - Queries all group nodes with `field_group_status` set to "Active" and status = 1
2. **Iterates through date range** - Processes every date from `start_date` to today
3. **Filters by group creation date** - Skips any dates before the group was created
4. **Matches meeting days** - For each date, checks if it matches a group's `field_meeting_day` (Sunday, Monday, Tuesday, etc.)
5. **Identifies missing records** - Determines if a `group_attendance_record` exists for that date and group
6. **Reports results** - Displays all missing attendance records in a formatted table

## Output Format
The command displays results in a table format showing:
- **Group ID**: The node ID of the group
- **Group Name**: The name/title of the group
- **Meeting Day**: The day of the week the group meets
- **Date**: The specific date that is missing an attendance record

### Example Output
```
 ---------- ---------------- ------------- ------------
  Group ID   Group Name       Meeting Day   Date
 ---------- ---------------- ------------- ------------
  73296      Boncore          Thursday      2025-01-02
  73451      Brooks/Selby     Thursday      2025-01-02
  2142       Larsen           Wednesday     2025-09-17
  16240      Tchombela        Saturday      2025-09-20
 ---------- ---------------- ------------- ------------
```

## Examples

### Check from January 1st to today
```bash
drush group-attendance-missing 2025-01-01
```

### Check from September 1st to today (within DDEV)
```bash
ddev drush group-attendance-missing 2025-09-01
```

### Check from a specific month to today
```bash
drush group-attendance-missing 2025-03-01
```

## Notes

### Performance Considerations
- The command iterates through every day in the date range for each active group
- For large date ranges (e.g., multiple years), this may take some time
- The query uses `accessCheck(FALSE)` to bypass permission checks for performance

### Active Groups Filter
- Only groups with `field_group_status` = "Active" and `status` = 1 are checked
- Groups with empty `field_meeting_day` values are skipped

### Attendance Record Query
- Only published (`status` = 1) attendance records are considered
- The `field_meeting_date` field is compared exactly against the date being checked

## Troubleshooting

### No missing records found
- This is normal - indicates all expected attendance records exist for the date range
- Groups may not have meetings scheduled for all their meeting days

### Invalid date format error
- Ensure the date uses Y-m-d format (e.g., 2025-01-15, not 1/15/2025 or 01-15-2025)

## Related Commands
- `pbc-automation:group-attendance-create` - Create a single attendance record for a specific group and date
- `pbc-automation:pco-refresh` - Refresh data from Planning Center Online

## Field Reference

### Group Node Fields Used
- `field_group_status`: Taxonomy term - must be "Active"
- `field_meeting_day`: List string - day of the week (Sunday-Saturday)

### Group Attendance Record Fields Used
- `field_group`: Entity reference to the group
- `field_meeting_date`: Date field - the date of the meeting
- `status`: Published/unpublished flag
