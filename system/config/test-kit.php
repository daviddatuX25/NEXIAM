<?php

return [
    'acceptance_sources' => [
        'two-item-count-v1' => [
            'provenance_kind' => 'synthetic_software_fixture',
            'source_sha256' => 'de0f2a7bc1199fc4eea5c90a3c864c61de546409a1fa4f1984c6c4148670cc33',
            'review_evidence_sha256' => '7a1c648685e8f277d3a6b14cdc68f2cf31a16531ef250ebd72b1226116f6ea3d',
            'reviewer_evidence_sha256' => '85e607c31f8f0e21118f52c6a710fd242997d7b390153d643f7d5cf9577533b6',
            'author_reference' => 'repository-artifact:tests/Fixtures/TestKit/two-item-count-v1.json; original author unknown',
            'truth_reference' => 'tests/Fixtures/TestKit/two-item-count-v1.json',
            'truth_sha256' => 'de0f2a7bc1199fc4eea5c90a3c864c61de546409a1fa4f1984c6c4148670cc33',
            'truth_attributed_to' => 'repository artifact; expected arithmetic independently hand-worked by AI software reviewer KitCriticalResume as documented in the review',
            'review_evidence_reference' => 'tests/Fixtures/TestKit/two-item-count-v1.review.json',
            'reviewer_reference' => 'tests/Fixtures/TestKit/two-item-count-v1.review.md',
        ],
        'two-item-count-native-v1' => [
            'provenance_kind' => 'synthetic_software_fixture',
            'source_sha256' => '5d3e30c40c747380bc64dfd8df0fcfbcd5ed0f141a43e1f8930c2c492438912b',
            'review_evidence_sha256' => 'b6b661d83ca5d6ea98199dd811afb9c11bd8a8494918a4dbaa2b5e45f29121a2',
            'reviewer_evidence_sha256' => 'bd5efc7559aef3102a38e87967bbd2c94c69435806b8b879e68956bdd2cf7ef8',
            'author_reference' => 'repository-artifact:tests/Fixtures/TestKit/two-item-count-native-v1.json; AI software fixture author KitNativeFixture',
            'truth_reference' => 'tests/Fixtures/TestKit/two-item-count-native-v1.json',
            'truth_sha256' => '5d3e30c40c747380bc64dfd8df0fcfbcd5ed0f141a43e1f8930c2c492438912b',
            'truth_attributed_to' => 'repository artifact; expected arithmetic independently hand-worked by AI software reviewer KitNativeFixtureReview as documented in the review',
            'review_evidence_reference' => 'tests/Fixtures/TestKit/two-item-count-native-v1.review.json',
            'reviewer_reference' => 'tests/Fixtures/TestKit/two-item-count-native-v1.review.md',
        ],
    ],
];
