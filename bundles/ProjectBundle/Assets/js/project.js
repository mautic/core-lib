Mautic.createProjects = function (el) {
    const newProjectNames = [];
    const existingProjectIds = [];
    const $projectSelect = mQuery(el);
    mQuery('#' + $projectSelect.attr('id') + ' :selected').each(function(i, selected) {
        const selectedId = mQuery(selected).val();

        // is text if new, int if existing.
        if (!mQuery.isNumeric(selectedId)) {
            newProjectNames.push(selectedId);
        } else {
            existingProjectIds.push(selectedId);
        }
    });

    if (!newProjectNames.length) {
        return;
    }

    Mautic.activateLabelLoadingIndicator($projectSelect.attr('id'));

    Mautic.ajaxActionRequest('project:addProjects', {newProjectNames: JSON.stringify(newProjectNames), existingProjectIds: JSON.stringify(existingProjectIds)}, function(response) {
        if (response.projects) {
            mQuery('#' + $projectSelect.attr('id')).html(response.projects).trigger('chosen:updated');
        }

        Mautic.removeLabelLoadingIndicator();
    });
};
