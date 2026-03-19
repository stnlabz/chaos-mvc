<?php require APPROOT . '/views/inc/head.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col">
            <h1><?= htmlspecialchars($data['title']) ?></h1>
            <p>
                <strong>Current Version</strong>: <?= htmlspecialchars($data['version']) ?><br>
                <strong>Status</strong>: <?= htmlspecialchars($data['status']) ?>
            </p>
            <p class="text-muted">
                This roadmap reflects active development. Each component shows its current state — what is stable, what is nearly complete, and what is still being built or planned.
            </p>
            <p class="text-muted">
                Progress reflects real-world usage and stability under load — not feature completion.
            </p>

            <hr>
        </div>
    </div>

    <?php
    /**
     * Component Confidence (Truth-Based)
     */
    $components = [
        'Core Engine' => [
            'value' => 100,
            'desc'  => 'Production-tested across multiple domains — stable under sustained traffic'
        ],
        'Module System' => [
            'value' => 100,
            'desc'  => 'Extensively used in live environments — stable and reliable'
        ],
        'Theme System' => [
            'value' => 100,
            'desc'  => 'Actively used across deployments — no instability observed'
        ],
        'Static Rendering' => [
            'value' => 100,
            'desc' => 'Serve static views with clean routing and layout control'
        ],
        'Markdown Rendering' => [
            'value' => 100,
            'desc' => 'Render Markdown content directly into views'
        ],
        'JSON Data Integration' => [
            'value' => 100,
            'desc' => 'Use JSON as a first-class data source across the system'
        ],
        'Database-Driven Content' => [
            'value' => 100,
            'desc' => 'Build dynamic content using MySQL-backed models'
        ],
        'Routing Engine' => [
            'value' => 100,
            'desc' => 'Map clean URLs directly to controllers'
        ],
        'Strict MVC Enforcement' => [
            'value' => 100,
            'desc' => 'Controller → Model → View with zero logic in views'
        ],
        'Installer' => [
            'value' => 85,
            'desc'  => 'Functionally complete — nearing full production confidence'
        ],
        'Updater Engine' => [
            'value' => 10,
            'desc'  => 'Early stage — not ready for real-world usage'
        ]
    ];

    /**
     * Calculate overall (simple average, no magic)
     */
    $values = array_column($components, 'value');
    $overall = count($values) ? round(array_sum($values) / count($values)) : 0;

    // Overall color
    if ($overall >= 90) {
        $overallBg = "#198754";
        $overallText = "#fff";
    } elseif ($overall >= 75) {
        $overallBg = "#0dcaf0";
        $overallText = "#000";
    } elseif ($overall >= 50) {
        $overallBg = "#ffc107";
        $overallText = "#000";
    } else {
        $overallBg = "#dc3545";
        $overallText = "#fff";
    }
    ?>

    <!-- OVERALL -->
    <div class="mb-5">
        <h3>Overall System Status</h3>

        <div class="progress-simple">
            <div 
                class="progress-bar-simple"
                style="width: <?= $overall ?>%; background: <?= $overallBg ?>; color: <?= $overallText ?>;">
                <?= $overall ?>%
            </div>
        </div>
    </div>

    <?php foreach ($components as $name => $info): 
        $val = $info['value'];

        // Color logic (truth-based zones)
        if ($val >= 90) {
            $bg = "#198754";
            $textColor = "#fff";
        } elseif ($val >= 75) {
            $bg = "#0dcaf0";
            $textColor = "#000";
        } elseif ($val >= 50) {
            $bg = "#ffc107";
            $textColor = "#000";
        } else {
            $bg = "#dc3545";
            $textColor = "#fff";
        }
    ?>

    <div class="mb-4">
        <h4><?= htmlspecialchars($name) ?></h4>
        <p class="text-muted mb-2"><?= htmlspecialchars($info['desc']) ?></p>

        <div class="progress-simple">
            <div 
                class="progress-bar-simple"
                style="width: <?= $val ?>%; background: <?= $bg ?>; color: <?= $textColor ?>;">
                <?= $val ?>%
            </div>
        </div>
    </div>

    <?php endforeach; ?>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
