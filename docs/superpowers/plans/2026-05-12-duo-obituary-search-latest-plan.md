# Duo Obituary Search and Latest List Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Duo obituary search use option-table values across all visible columns, and stop latest-list rows from being clipped when rolling is inactive or search filters the rows.

**Architecture:** Keep KBoard's existing `keyword` GET flow. Extend the existing Duo skin query hooks with shared option-search helpers used by both normal and latest lists. Keep latest rolling as a view behavior: only rolling state gets a fixed clipped viewport.

**Tech Stack:** PHP CLI smoke tests, KBoard skin hooks, WordPress-style `$wpdb` query helpers, CSS, vanilla JavaScript.

---

### Task 1: Option-Table Search

**Files:**
- Modify: `tests/smoke.php`
- Modify: `skin/duo-obituary-kboard/functions.php`
- Modify: `plugins/duo-obituary-kboard/skins/duo-obituary-kboard/functions.php`

- [ ] **Step 1: Write the failing test**

In `tests/smoke.php` skin mode, replace the old custom `duo_obituary_target` / `duo_obituary_keyword` setup with KBoard's real `keyword` parameter and assert every visible option alias participates in search:

```php
$_GET = array('keyword' => '홍');
$from = duo_obituary_add_query_joins('`wp_kboard_board_content`', 1, $list);
foreach (array('duo_affiliation', 'duo_deceased_name', 'duo_chief_mourner', 'duo_death_date', 'duo_coffin_date', 'duo_funeral_date', 'duo_place', 'duo_burial_place') as $alias) {
	contains_text($from, $alias, "List query should join {$alias} option.");
}

$where = duo_obituary_query_where('1=1', 1, $list);
ok(strpos($where, ">= '2026-05-07'") === false, 'Public list query should show expired obituaries.');
foreach (array('duo_affiliation', 'duo_deceased_name', 'duo_chief_mourner', 'duo_death_date', 'duo_coffin_date', 'duo_funeral_date', 'duo_place', 'duo_burial_place') as $alias) {
	contains_text($where, "{$alias}.`option_value` LIKE", "Integrated search should include {$alias}.");
}

$latest_list = (object)array('board' => (object)array('skin' => 'Duo 부고알림'), 'is_latest' => true);
$latest_where = duo_obituary_query_where('1=1', 1, $latest_list);
contains_text($latest_where, "DATE(STR_TO_DATE(duo_funeral_date.`option_value`, '%Y-%m-%d %H:%i')) >= '2026-05-07'", 'Latest query should hide expired obituaries.');
contains_text($latest_where, 'duo_place.`option_value` LIKE', 'Latest search should include funeral hall option.');
contains_text($latest_where, 'duo_burial_place.`option_value` LIKE', 'Latest search should include burial place option.');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/smoke.php skin`

Expected: FAIL because at least `duo_affiliation` is not joined or not present in the search WHERE.

- [ ] **Step 3: Write minimal implementation**

In both `functions.php` copies, add a helper for searchable option aliases, update `duo_obituary_add_query_joins()` to join all aliases, and update `duo_obituary_query_where()` to append a prepared OR group when `kboard_keyword()` returns a non-empty value.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/smoke.php skin`

Expected: PASS.

### Task 2: Latest No-Clipping Layout

**Files:**
- Modify: `tests/smoke.php`
- Modify: `skin/duo-obituary-kboard/style.css`
- Modify: `skin/duo-obituary-kboard/script.js`
- Modify: `plugins/duo-obituary-kboard/skins/duo-obituary-kboard/style.css`
- Modify: `plugins/duo-obituary-kboard/skins/duo-obituary-kboard/script.js`

- [ ] **Step 1: Write the failing test**

In `tests/smoke.php` skin mode, add static assertions after the PHP behavior assertions:

```php
$style = file_get_contents($root . '/skin/duo-obituary-kboard/style.css');
$script = file_get_contents($root . '/skin/duo-obituary-kboard/script.js');
contains_text($style, '.is-rolling .is-rolling-container', 'Latest rolling viewport height should only apply while rolling.');
ok(strpos($style, ".is-rolling-container {\n\theight: 260px;") === false, 'Latest container should not have unconditional fixed height.');
contains_text($style, 'min-height: 52px;', 'Latest rows should have a minimum row height instead of clipping wrapped content.');
contains_text($script, 'getBoundingClientRect().height', 'Rolling distance should use actual visible row heights.');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/smoke.php skin`

Expected: FAIL because `.is-rolling-container` currently has unconditional `height: 260px`.

- [ ] **Step 3: Write minimal implementation**

Move fixed viewport height under rolling state CSS, change latest row fixed heights to `min-height` where table layout allows, and compute JS animation distance from visible row heights.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/smoke.php skin`

Expected: PASS.

### Task 3: Full Smoke and Package Sync

**Files:**
- Verify: `skin/duo-obituary-kboard/*`
- Verify: `plugins/duo-obituary-kboard/skins/duo-obituary-kboard/*`
- Verify: `tests/smoke.sh`

- [ ] **Step 1: Run full smoke**

Run: `bash tests/smoke.sh`

Expected: `Smoke tests passed.`

- [ ] **Step 2: Verify changed skin files are synchronized**

Run:

```bash
diff -u skin/duo-obituary-kboard/functions.php plugins/duo-obituary-kboard/skins/duo-obituary-kboard/functions.php
diff -u skin/duo-obituary-kboard/style.css plugins/duo-obituary-kboard/skins/duo-obituary-kboard/style.css
diff -u skin/duo-obituary-kboard/script.js plugins/duo-obituary-kboard/skins/duo-obituary-kboard/script.js
```

Expected: no output from each diff.

