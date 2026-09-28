// Plain helpers behind LabsView.vue, kept out of the SFC so `node --test tests/js`
// can run them without a Vue test harness.

/**
 * /lab-panels pages at 25 while /lab-results pages at 50, so one page can miss
 * panels the loaded results belong to. Walk the pages (newest first, like the
 * results) until every id in `ids` is found or the pages run out.
 */
export async function loadPanels(get, ids) {
    const want = new Set(ids.filter(id => id !== null && id !== undefined));
    const panels = {};
    for (let page = 1; ; page++) {
        const res = await get('/lab-panels', { page });
        for (const p of res.data.data) { panels[p.id] = p; want.delete(p.id); }
        if (!want.size || page >= (res.data.meta?.last_page ?? 1)) return panels;
    }
}

/**
 * Group the newest-first result list by panel, keeping first-seen order so the
 * groups stay newest-first too. Results with no lab_order_id carry no panel
 * (see LabResultImporter::resolvePanel), so they fall into a trailing bucket.
 */
export function groupResults(results, panels) {
    const out = [];
    const byId = new Map();
    for (const r of results) {
        const id = r.lab_panel_id ?? 'none';
        if (!byId.has(id)) {
            const p = panels[r.lab_panel_id];
            byId.set(id, {
                id,
                // A panel id we could not load is still a panel: never call it "not part of one".
                title: p?.name || p?.performing_org || (p ? `Lab order ${p.lab_order_id}`
                    : r.lab_panel_id == null ? 'Not part of a panel' : 'Lab panel'),
                date: p?.collected_at ?? r.sampled_at,
                rows: [],
            });
            out.push(byId.get(id));
        }
        byId.get(id).rows.push(r);
    }
    return out;
}
