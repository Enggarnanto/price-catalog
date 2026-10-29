# Snapshot schema 5

Table snapshots may add `table_options` to the existing schema 2/3/4 fields. Catalog snapshots retain the previous schemas. Old snapshots are not rewritten.

```json
{
  "currency": "IDR",
  "decimals": 0,
  "number_format": "id-ID",
  "price_columns": [1],
  "mobile_orientation": "inherit",
  "freeze_first_column": true
}
```

All six keys are required. Currency is IDR, USD, EUR, GBP, SGD, MYR, AUD or JPY; decimals is 0 or 2; number_format is id-ID or en-US. Price columns are unique zero-based column indexes in the source grid. Mobile orientation is inherit, vertical or horizontal. Freeze is a boolean.

Nominal text is parsed using the chosen number format and rendered with its currency symbol/grouping/decimal setting. Existing stored text and URLs remain unchanged. Mixed text or unsupported numeric syntax remains literal. This feature does not perform exchange-rate conversion. Column indexes follow editor column moves/deletions and are bounded after paste/import.

When mobile orientation differs from desktop, the server produces two table views and CSS displays exactly one at a 700px breakpoint. Both orientations support horizontal panel scrolling and a sticky first header/body column with an opaque background. The hidden view uses display:none. No public JavaScript is required.

The editor adds these settings when a table is edited; publishing is required to change public output. Old snapshots keep their old renderer behavior. Restore continues to use immutable snapshot content. Do not downgrade to versions before 2.1.0 after saving schema 5.

Database schema version 4 separately adds cache-event types and changes its unique key to (version_id,event_type), so publish/unpublish have distinct event IDs. This migration preserves prior events and snapshots. Unpublish keeps or prepares a draft, clears its production base, withdraws the production pointer and queues invalidation in one verified transaction. Preparing a draft alone does not withdraw a published price.
