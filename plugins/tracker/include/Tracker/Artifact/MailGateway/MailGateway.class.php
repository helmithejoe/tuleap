<?php
/**
 * Copyright (c) Enalean, 2014-2015. All Rights Reserved.
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

class Tracker_Artifact_MailGateway_MailGateway {

    /**
     * @var Tracker_Artifact_MailGateway_CitationStripper
     */
    private $citation_stripper;

    /**
     * @var Tracker_Artifact_MailGateway_Parser
     */
    private $parser;

    /**
     * @var Tracker_Artifact_MailGateway_IncomingMessageFactory
     */
    private $incoming_message_factory;

    /**
     * @var Tracker_Artifact_MailGateway_Notifier
     */
    private $notifier;

    /**
     * @var Tracker_ArtifactFactory
     */
    private $artifact_factory;

    /**
     * @var TrackerPluginConfig
     */
    private $tracker_plugin_config;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        Tracker_Artifact_MailGateway_Parser $parser,
        Tracker_Artifact_MailGateway_IncomingMessageFactory $incoming_message_factory,
        Tracker_Artifact_MailGateway_CitationStripper $citation_stripper,
        Tracker_Artifact_MailGateway_Notifier $notifier,
        Tracker_ArtifactFactory $artifact_factory,
        TrackerPluginConfig $tracker_plugin_config,
        Logger $logger
    ) {
        $this->logger                   = $logger;
        $this->parser                   = $parser;
        $this->incoming_message_factory = $incoming_message_factory;
        $this->citation_stripper        = $citation_stripper;
        $this->notifier                 = $notifier;
        $this->artifact_factory         = $artifact_factory;
        $this->tracker_plugin_config    = $tracker_plugin_config;
    }

    public function process($raw_mail) {
        $raw_mail_parsed = $this->parser->parse($raw_mail);
        try {
            $incoming_message = $this->incoming_message_factory->build($raw_mail_parsed);
            $body             = $this->citation_stripper->stripText($incoming_message->getBody());

            $tracker_artifactbyemail = new Tracker_ArtifactByEmailStatus(
                $incoming_message->getTracker(),
                $this->tracker_plugin_config
            );
            if ($tracker_artifactbyemail->canCreateArtifact() || $tracker_artifactbyemail->canUpdateArtifact()) {
                $this->createChangeset($incoming_message, $body);
            } else {
                $this->logger->info(
                    'An artifact for the tracker #' . $incoming_message->getTracker()->getId() .
                    ' has been received but this tracker does not allow create/reply by mail or' .
                    ' his configuration is not compatible with this feature'
                );
                $this->notifier->sendErrorMailTrackerGeneric($raw_mail_parsed);
            }
        } catch (Tracker_Artifact_MailGateway_MultipleUsersExistException $e) {
            $this->logger->debug('Multiple users match with ' . $raw_mail_parsed['headers']['from']);
            $this->notifier->sendErrorMailMultipleUsers($raw_mail_parsed);
        } catch(Tracker_Artifact_MailGateway_RecipientUserDoesNotExistException $e) {
            $this->logger->debug('No user match with ' . $raw_mail_parsed['headers']['from']);
            $this->notifier->sendErrorMailNoUserMatch($raw_mail_parsed);
        } catch (Tracker_Exception $e) {
            $this->logger->error($e->getMessage());
            $this->notifier->sendErrorMailTrackerGeneric($raw_mail_parsed);
        }
    }

    private function createChangeset(Tracker_Artifact_MailGateway_IncomingMessage $incoming_message, $body) {
        if ($incoming_message->isAFollowUp()) {
            $this->addFollowUp($incoming_message->getUser(), $incoming_message->getArtifact(), $body);
        } else {
            $this->createArtifact(
                $incoming_message->getUser(),
                $incoming_message->getTracker(),
                $incoming_message->getSubject(),
                $body
            );
        }
    }

    private function addFollowUp(PFUser $user, Tracker_Artifact $artifact, $body) {
        $this->logger->debug("Receiving new follow-up comment from ". $user->getUserName());

        if (! $artifact->userCanUpdate($user)) {
            $this->logger->info("User ". $user->getUnixName() ." has no right to update the artifact #" . $artifact->getId());
            $this->notifier->sendErrorMailInsufficientPermissionUpdate($user->getEmail(), $artifact->getId());
            return;
        }

        $artifact->createNewChangeset(
            array(),
            $body,
            $user,
            true,
            Tracker_Artifact_Changeset_Comment::TEXT_COMMENT
        );
    }

    private function createArtifact(PFUser $user, Tracker $tracker, $title, $body) {
        $this->logger->debug("Receiving new artifact from ". $user->getUserName());

        if (! $tracker->userCanSubmitArtifact($user)) {
            $this->logger->info("User ". $user->getUnixName() ." has no right to create an artifact in tracker #" . $tracker->getId());
            $this->notifier->sendErrorMailInsufficientPermissionCreation($user->getEmail(), $title);
            return;
        }

        $title_field       = $tracker->getTitleField();
        $description_field = $tracker->getDescriptionField();
        if (! $title_field || ! $description_field) {
            throw new Tracker_Artifact_MailGateway_TrackerMissingSemanticException();
        }

        $field_data = array(
            $title_field->getId()       => $title,
            $description_field->getId() => $body
        );

        UserManager::instance()->setCurrentUser($user);
        $this->artifact_factory->createArtifact($tracker, $field_data, $user, '');
    }
}
