//StageBundle
Mautic.stageOnLoad = function (container, response) {
    if (mQuery(container + ' #stage-weight-sequence').length) {
        const stageWeights = mQuery('#stage-weight-sequence').data('weights');
        const weightFieldId = mQuery('#stage-weight-sequence').data('weight-field-id');
        const entityId = mQuery('#stage-weight-sequence').data('entity-id');

        if (stageWeights && weightFieldId) {
            Mautic.initStageWeightConflictCheck(stageWeights, weightFieldId, entityId);
        }
    }
};

Mautic.getStageActionPropertiesForm = function(actionType) {
    Mautic.activateLabelLoadingIndicator('stage_type');

    var query = "action=stage:getActionForm&actionType=" + actionType;
    mQuery.ajax({
        url: mauticAjaxUrl,
        type: "POST",
        data: query,
        dataType: "json",
        success: function (response) {
            if (typeof response.html != 'undefined') {
                mQuery('#stageActionProperties').html(response.html);
                Mautic.onPageLoad('#stageActionProperties', response);
            }
        },
        error: function (request, textStatus, errorThrown) {
            Mautic.processAjaxError(request, textStatus, errorThrown);
        },
        complete: function() {
            Mautic.removeLabelLoadingIndicator();
        }
    });
};

Mautic.initStageWeightConflictCheck = function(weights, weightFieldId, entityId) {
    mQuery(document).ready(function() {
        const weightField = mQuery('#' + weightFieldId);
        const group = weightField.closest('.form-group');
        const message = mQuery('<span class="help-block text-danger"></span>').insertAfter(weightField);

        function check() {
            const val = parseInt(weightField.val(), 10);
            const conflict = weights.some(function(w) {
                return w.weight == val && (!entityId || w.id != entityId);
            });

            if (conflict) {
                group.addClass('has-error');
                message.text(Mautic.translate('mautic.stage.weight.conflict'));
            } else {
                group.removeClass('has-error');
                message.text('');
            }
        }

        weightField.on('input', check);
        check();
    });
};