<?php
// Temporary driver: docker compose run does not forward host env, and the
// helper below reads its action from the environment.
putenv("MDS_ADVERTISER_FIXTURE_ACTION=seed");
require __DIR__ . '/advertiser-page-browser-fixture.php';
