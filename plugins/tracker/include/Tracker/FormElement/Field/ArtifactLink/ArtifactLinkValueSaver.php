<?php
/**
 * Copyright (c) Enalean, 2016. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Tuleap\Tracker\FormElement\Field\ArtifactLink;

use Tracker_Artifact;
use Tracker_ArtifactLinkInfo;
use Tracker_Artifact_ChangesetValue_ArtifactLink;
use Tracker_ArtifactFactory;
use Tracker_FormElement_Field_Value_ArtifactLinkDao;
use Tracker_ReferenceManager;
use Tracker_FormElement_Field_ArtifactLink;
use Tracker;
use Tracker_Artifact_ChangesetValue;
use PFUser;

class ArtifactLinkValueSaver {

    /**
     * @var Tracker_ReferenceManager
     */
    private $reference_manager;

    /**
     * @var Tracker_FormElement_Field_Value_ArtifactLinkDao
     */
    private $dao;

    /**
     * @var Tracker_ArtifactFactory
     */
    private $artifact_factory;

    public function __construct(
        Tracker_ArtifactFactory $artifact_factory,
        Tracker_FormElement_Field_Value_ArtifactLinkDao $dao,
        Tracker_ReferenceManager $reference_manager
    ) {
        $this->artifact_factory  = $artifact_factory;
        $this->dao               = $dao;
        $this->reference_manager = $reference_manager;
    }

    /**
     * Save the value
     *
     * @param Tracker_FormElement_Field_ArtifactLink $field              The field in which we save the value
     * @param PFUser                                 $user               The current user
     * @param Tracker_Artifact                       $artifact           The artifact
     * @param int                                    $changeset_value_id The id of the changeset_value
     * @param mixed                                  $submitted_value    The value submitted by the user
     */
    public function saveValue(
        Tracker_FormElement_Field_ArtifactLink $field,
        PFUser $user,
        Tracker_Artifact $artifact,
        $changeset_value_id,
        array $submitted_value
    ) {
        $artifact_ids_to_link = $this->getArtifactIdsToLink($field->getTracker(), $artifact, $submitted_value);
        foreach ($artifact_ids_to_link as $artifact_to_be_linked_by_tracker) {
            $tracker = $artifact_to_be_linked_by_tracker['tracker'];

            foreach ($artifact_to_be_linked_by_tracker['natures'] as $nature => $ids) {
                if (! $nature) {
                    $nature = null;
                }

                $this->dao->create(
                    $changeset_value_id,
                    $nature,
                    $ids,
                    $tracker->getItemName(),
                    $tracker->getGroupId()
                );
            }
        }

        return $this->updateCrossReferences($user, $artifact, $submitted_value);
    }

    private function getNature(Tracker_ArtifactLinkInfo $artifactlinkinfo, Tracker $from_tracker, Tracker $to_tracker) {
        if (in_array($to_tracker, $from_tracker->getChildren())) {
            return Tracker_FormElement_Field_ArtifactLink::NATURE_IS_CHILD;
        }

        $existing_nature = $artifactlinkinfo->getNature();
        if (! empty($existing_nature)) {
            return $existing_nature;
        }

        return null;
    }

    /**
     * Update cross references of this field
     *
     * @param Tracker_Artifact $artifact the artifact that is currently updated
     * @param array            $submitted_value   the array of added and removed artifact links ($values['added_values'] is a string and $values['removed_values'] is an array of artifact ids
     *
     * @return boolean
     */
    private function updateCrossReferences(PFUser $user, Tracker_Artifact $artifact, array $submitted_value) {
        $update_ok = true;

        foreach ($this->getAddedArtifactIds($submitted_value) as $added_artifact_id) {
            $update_ok = $update_ok && $this->insertCrossReference($user, $artifact, $added_artifact_id);
        }
        foreach ($this->getRemovedArtifactIds($submitted_value) as $removed_artifact_id) {
            $update_ok = $update_ok && $this->removeCrossReference($user, $artifact, $removed_artifact_id);
        }

        return $update_ok;
    }

    private function canLinkArtifacts(Tracker_Artifact $src_artifact, Tracker_Artifact $artifact_to_link) {
        return ($src_artifact->getId() != $artifact_to_link->getId()) && $artifact_to_link->getTracker();
    }

    private function getAddedArtifactIds(array $values) {
        $ids = array();
        foreach ($values['list_of_artifactlinkinfo'] as $artifactlinkinfo) {
            $ids[] = (int) $artifactlinkinfo->getArtifactId();
        }

        return $ids;
    }

    private function getRemovedArtifactIds(array $values) {
        if (array_key_exists('removed_values', $values)) {
            return array_map('intval', array_keys($values['removed_values']));
        }
        return array();
    }

    private function insertCrossReference(PFUser $user, Tracker_Artifact $source_artifact, $target_artifact_id) {
        return $this->reference_manager->insertBetweenTwoArtifacts(
            $source_artifact,
            $this->artifact_factory->getArtifactById($target_artifact_id),
            $user
        );
    }

    private function removeCrossReference(PFUser $user, Tracker_Artifact $source_artifact, $target_artifact_id) {
        return $this->reference_manager->removeBetweenTwoArtifacts(
            $source_artifact,
            $this->artifact_factory->getArtifactById($target_artifact_id),
            $user
        );
    }

    /** @return {'tracker' => Tracker, 'ids' => int[]}[] */
    private function getArtifactIdsToLink(
        Tracker $from_tracker,
        Tracker_Artifact $artifact,
        array $submitted_value
    ) {
        $all_artifact_to_be_linked = array();
        foreach ($submitted_value['list_of_artifactlinkinfo'] as $artifactlinkinfo) {
            $artifact_to_link = $artifactlinkinfo->getArtifact();
            if ($this->canLinkArtifacts($artifact, $artifact_to_link)) {
                $tracker = $artifact_to_link->getTracker();
                $nature  = $this->getNature($artifactlinkinfo, $from_tracker, $tracker);

                if (! isset($all_artifact_to_be_linked[$tracker->getId()])) {
                    $all_artifact_to_be_linked[$tracker->getId()] = array(
                        'tracker' => $tracker,
                        'natures' => array()
                    );
                }

                if (! isset($all_artifact_to_be_linked[$tracker->getId()]['natures'][$nature])) {
                    $all_artifact_to_be_linked[$tracker->getId()]['natures'][$nature] = array();
                }

                $all_artifact_to_be_linked[$tracker->getId()]['natures'][$nature][] = $artifact_to_link->getId();
            }
        }

        return $all_artifact_to_be_linked;
    }
}