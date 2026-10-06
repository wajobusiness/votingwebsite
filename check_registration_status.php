<?php
/**
 * Public Competition Status Polling Endpoint
 */

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

echo json_encode([
    'open'        => Settings::isRegistrationOpen(),
    'voting_open' => Settings::isVotingOpen(),
    'stage'       => Settings::getCurrentStage(),
    'end_time'    => Settings::getCompetitionEndTime()
]);
exit();
