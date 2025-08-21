<?php

declare(strict_types=1);

namespace Mautic\ProjectBundle;

/**
 * Contains all events dispatched by ProjectBundle.
 */
final class ProjectEvents
{
    /**
     * Event dispatched to allow bundles to extend entity type normalization mappings.
     */
    public const ENTITY_TYPE_NORMALIZATION = 'mautic.project.entity_type_normalization';

    /**
     * Event dispatched to allow bundles to extend entity type to model key mappings.
     */
    public const ENTITY_TYPE_MODEL_MAPPING = 'mautic.project.entity_type_model_mapping';
}
