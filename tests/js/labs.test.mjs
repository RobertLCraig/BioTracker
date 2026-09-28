// Run with: node --test tests/js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { loadResults, loadPanels, groupResults } from '../../resources/js/labs.js';

// A fake of useApi().get for /lab-panels, paginated at 25 like LabPanelController::index.
function pagedPanels(count, perPage = 25) {
    const all = Array.from({ length: count }, (_, i) => ({ id: i + 1, lab_order_id: `ORD${i + 1}`, name: `Panel ${i + 1}` }));
    const lastPage = Math.max(1, Math.ceil(count / perPage));
    const calls = [];
    const get = async (path, params = {}) => {
        const page = params.page ?? 1;
        calls.push(page);
        return { data: { data: all.slice((page - 1) * perPage, page * perPage), meta: { current_page: page, last_page: lastPage, total: count } } };
    };
    return { get, calls };
}

test('panels_past_the_first_page_are_loaded_for_the_results_that_need_them', async () => {
    const { get } = pagedPanels(30);
    const panels = await loadPanels(get, [1, 26, 30]);
    assert.equal(panels[26]?.name, 'Panel 26');
    assert.equal(panels[30]?.name, 'Panel 30');
});

test('panel_pages_stop_once_every_needed_panel_is_found', async () => {
    const { get, calls } = pagedPanels(100);
    await loadPanels(get, [3, 20]);
    assert.deepEqual(calls, [1]);
});

// A fake of useApi().get for /lab-results, paginated at 50 like LabResultController::index.
function pagedResults(count, perPage = 50) {
    const all = Array.from({ length: count }, (_, i) => ({ id: i + 1, lab_panel_id: null }));
    const lastPage = Math.max(1, Math.ceil(count / perPage));
    const get = async (path, params = {}) => {
        const page = params.page ?? 1;
        return { data: { data: all.slice((page - 1) * perPage, page * perPage), meta: { current_page: page, last_page: lastPage, total: count } } };
    };
    return { get };
}

test('every_result_is_listed_not_only_the_first_50_row_page', async () => {
    const { get } = pagedResults(120);
    const rows = await loadResults(get);
    assert.equal(rows.length, 120);
    assert.deepEqual(rows.map(r => r.id), Array.from({ length: 120 }, (_, i) => i + 1));
});

test('a_row_repeated_across_a_page_edge_is_listed_once', async () => {
    // Rows sharing one sampled_at have no fixed order, so page 2 can repeat page 1's last row.
    const pages = [[{ id: 1 }, { id: 2 }], [{ id: 2 }, { id: 3 }]];
    const get = async (path, { page }) => ({ data: { data: pages[page - 1], meta: { last_page: 2 } } });
    const rows = await loadResults(get);
    assert.deepEqual(rows.map(r => r.id), [1, 2, 3]);
});

test('a_result_whose_panel_is_not_loaded_is_not_headed_as_having_no_panel', () => {
    const groups = groupResults([{ id: 1, lab_panel_id: 99, sampled_at: '2026-01-01' }], {});
    assert.notEqual(groups[0].title, 'Not part of a panel');
});

test('a_result_with_no_panel_is_headed_as_not_part_of_a_panel', () => {
    const groups = groupResults([{ id: 1, lab_panel_id: null, sampled_at: '2026-01-01' }], {});
    assert.equal(groups[0].title, 'Not part of a panel');
});
