# Duo Obituary Search and Latest List Design

## Problem

The Duo obituary KBoard skin shows no visible change after a search. The current search form submits `keyword`, but the displayed obituary data mostly lives in `kboard_board_option`, not only in the KBoard content table. As a result, KBoard's default title/content search is not reliable for the fields users see in the list.

The latest-post display has a separate visual issue. Its rolling container assumes five fixed 52px rows and uses `overflow: hidden`. When text wraps, or when search leaves five or fewer rows and rolling stops, the fixed clipping can cut off table cells.

## Goals

- Search every column visible in the main list: affiliation, deceased name, chief mourner, death date, coffin date, funeral date, funeral hall, and burial place.
- Apply the same search behavior to the latest-post display.
- Preserve existing KBoard URLs and the single `keyword` search input.
- Keep the latest-post date filtering behavior: latest results remain limited to active/current obituary items.
- Prevent cell clipping when the latest-post display is not rolling, has five or fewer rows, or is filtered by search.
- Keep changes scoped to the Duo obituary skin source and packaged plugin copy.

## Non-Goals

- Do not add advanced search controls, filters, or separate field selectors.
- Do not introduce a new search index table or background indexing job.
- Do not refactor unrelated KBoard behavior.
- Do not change the visible table columns beyond making their existing values searchable and unclipped.

## Search Design

The search form continues to submit `keyword` through KBoard's normal GET flow. The skin will extend the existing query filters instead of replacing KBoard's list builder.

`duo_obituary_add_query_joins()` will join `kboard_board_option` once per searchable option key with stable aliases:

- `duo_affiliation`
- `duo_deceased_name`
- `duo_chief_mourner`
- `duo_death_date`
- `duo_coffin_date`
- `duo_funeral_date`
- `duo_place`
- `duo_burial_place`

`duo_obituary_query_where()` will read `kboard_keyword()`. When the keyword is empty, it keeps the current behavior. When the keyword is present, it appends one grouped OR condition across all searchable aliases:

```sql
(
  duo_affiliation.option_value LIKE %keyword%
  OR duo_deceased_name.option_value LIKE %keyword%
  OR duo_chief_mourner.option_value LIKE %keyword%
  OR duo_death_date.option_value LIKE %keyword%
  OR duo_coffin_date.option_value LIKE %keyword%
  OR duo_funeral_date.option_value LIKE %keyword%
  OR duo_place.option_value LIKE %keyword%
  OR duo_burial_place.option_value LIKE %keyword%
)
```

The condition will use `$wpdb->esc_like()` and `$wpdb->prepare()` for each `LIKE` value. The helper that builds this condition should be shared by normal list and latest list filtering.

The existing filters already connect this logic to both views:

- `kboard_list_from` and `kboard_list_where`
- `kboard_latest_from` and `kboard_latest_where`

The latest query must keep its current active-obituary condition based on funeral date. Searching latest posts should therefore mean "search within latest/current obituary rows", not "search all historical rows".

## Latest List Layout Design

The latest list should only clip rows while rolling is active. When rolling is inactive, the table body must expand to its content height.

CSS changes:

- Remove unconditional fixed height from `.is-rolling-container`.
- Apply the 5-row clipping height only under `.is-rolling`.
- Use `height` for the rolling viewport only, and use `min-height` or normal content height for rows so wrapped text can grow when not rolling.
- Keep desktop and mobile latest tables aligned with their headers.
- Keep empty and no-result rows visible without clipping.

JavaScript changes:

- Continue disabling rolling when visible rows are five or fewer or when the latest search input has a value.
- Reset transforms and cancel animations when rolling is disabled.
- Avoid assuming clipped layout when rolling is disabled.
- For rolling mode, compute animation distance from actual visible row heights or otherwise keep the fixed-height assumption confined to rows that are known to be clipped to the rolling viewport.

The current client-side latest search may remain as an instant in-page filter for already-rendered latest rows. It must not force the rolling container to keep a clipped height after search.

## Files

Primary source skin:

- `skin/duo-obituary-kboard/functions.php`
- `skin/duo-obituary-kboard/latest.php` if template state or classes need small adjustments
- `skin/duo-obituary-kboard/style.css`
- `skin/duo-obituary-kboard/script.js`

Packaged plugin copy:

- `plugins/duo-obituary-kboard/skins/duo-obituary-kboard/functions.php`
- `plugins/duo-obituary-kboard/skins/duo-obituary-kboard/latest.php` if changed in source
- `plugins/duo-obituary-kboard/skins/duo-obituary-kboard/style.css`
- `plugins/duo-obituary-kboard/skins/duo-obituary-kboard/script.js`

The two skin directories should remain synchronized for changed files.

## Verification

- Main list search narrows results by affiliation.
- Main list search narrows results by deceased name.
- Main list search narrows results by chief mourner.
- Main list search narrows results by funeral hall or burial place.
- Latest-post search applies the same fields.
- Latest-post display with five or fewer rows does not roll and does not clip cells.
- Latest-post display after search does not clip cells, including wrapped text.
- Latest-post display with more than five rows still rolls.
