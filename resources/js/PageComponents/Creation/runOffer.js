// The small show run switch offered when duplicating: add the copy to this
// event's own show run (on to begin with), or start one from a live event
// that is in none (off to begin with). A draft gets none.
export const runOfferFor = (event) => {
    if (event?.run_of) return { title: `Add to ${event.run_of.name}`, line: 'Shows the copy with this show\'s other runs, on one page.', on: true };
    if (event?.status === 'p' && !event?.project_id) return { title: 'Same show, new run?', line: 'Turn this on to show both on one page. Good for a show that tours or comes back.', on: false };
    return null;
};
