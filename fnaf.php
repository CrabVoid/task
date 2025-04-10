<?php
session_start();

// Initialize game state
if (!isset($_SESSION['game_state'])) {
    $_SESSION['game_state'] = [
        'night' => 1,
        'max_nights' => 5,
        'power' => 100,
        'animatronics' => [
            'freddy' => [
                'position' => 0,
                'aggression' => 1,
                'last_moved' => 0,
                'is_hidden' => false,
                'laugh_played' => false
            ],
            'bonnie' => [
                'position' => 0,
                'aggression' => 1,
                'last_moved' => 0,
                'prefers_left' => true
            ],
            'chica' => [
                'position' => 0,
                'aggression' => 1,
                'last_moved' => 0,
                'prefers_right' => true,
                'in_kitchen' => false
            ],
            'foxy' => [
                'position' => 0,
                'aggression' => 1,
                'last_moved' => 0,
                'run_cooldown' => 0,
                'stage' => 1 // 1-4 (1=in cove, 4=running)
            ]
        ],
        'cameras' => 'office',
        'left_door_closed' => false,
        'right_door_closed' => false,
        'time' => 0,
        'game_over' => false,
        'win' => false,
        'message' => '',
        'audio_playing' => ''
    ];
}

$state = &$_SESSION['game_state'];

// Night-specific difficulty adjustments
$difficulty_settings = [
    1 => ['power_drain' => 4, 'move_chance' => 15, 'foxy_active' => false],
    2 => ['power_drain' => 5, 'move_chance' => 17, 'foxy_active' => true],
    3 => ['power_drain' => 6, 'move_chance' => 19, 'foxy_active' => true],
    4 => ['power_drain' => 7, 'move_chance' => 21, 'foxy_active' => true],
    5 => ['power_drain' => 8, 'move_chance' => 23, 'foxy_active' => true]
];

// Process actions
if (isset($_GET['action']) && !$state['game_over']) {
    $state['message'] = '';
    $state['audio_playing'] = '';

    switch ($_GET['action']) {
        case 'use_camera':
            $location = $_GET['location'];
            $state['cameras'] = $location;
            $state['power'] -= 1;
            $state['message'] = "Checking " . str_replace('_', ' ', $location) . "...";

            // When checking Pirate Cove, reset Foxy's stage if he hasn't run yet
            if ($location == 'pirate_cove' && isset($state['animatronics']['foxy']) && is_array($state['animatronics']['foxy']) && isset($state['animatronics']['foxy']['stage']) && $state['animatronics']['foxy']['stage'] < 4) {
                $state['animatronics']['foxy']['stage'] = 1;
                $state['animatronics']['foxy']['run_cooldown'] = 3;
            }
            break;

        case 'close_camera':
            $state['cameras'] = 'office';
            break;

        case 'toggle_left_door':
            $state['left_door_closed'] = !$state['left_door_closed'];
            $state['message'] = $state['left_door_closed'] ? "Left door closed!" : "Left door opened!";
            $state['audio_playing'] = $state['left_door_closed'] ? 'door_close' : 'door_open';
            break;

        case 'toggle_right_door':
            $state['right_door_closed'] = !$state['right_door_closed'];
            $state['message'] = $state['right_door_closed'] ? "Right door closed!" : "Right door opened!";
            $state['audio_playing'] = $state['right_door_closed'] ? 'door_close' : 'door_open';
            break;

        case 'next_hour':
            $state['time'] += 1;
            $current_night = $state['night'];
            if (isset($difficulty_settings[$current_night]['power_drain'])) {
                $state['power'] -= $difficulty_settings[$current_night]['power_drain'];
            }

            // Process each animatronic's AI
            processAnimatronicAI();

            // Check for game over conditions
            checkGameOverConditions();

            // Check for win condition
            if ($state['time'] >= 6) {
                $state['game_over'] = true;
                $state['win'] = true;
                if ($state['night'] < $state['max_nights']) {
                    $state['message'] = "You survived night {$state['night']}! Ready for night " . ($state['night'] + 1) . "?";
                } else {
                    $state['message'] = "Congratulations! You survived all five nights!";
                }
                $state['audio_playing'] = '6am';
            }
            break;

        case 'next_night':
            if ($state['night'] < $state['max_nights']) {
                $state['night'] += 1;
                resetForNewNight();
            }
            break;

        case 'restart':
            session_destroy();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
    }
}

function processAnimatronicAI()
{
    global $state;
    $current_night = $state['night'];

    // Freddy's AI - becomes more active after specific times and when not looked at
    if (isset($state['animatronics']['freddy']) && is_array($state['animatronics']['freddy'])) {
        $freddy = &$state['animatronics']['freddy'];
        if ($state['time'] >= 3 && rand(1, 100) < (10 * $current_night)) {
            // Freddy can teleport when not being watched
            if ($state['cameras'] != 'show_stage' && !$freddy['is_hidden']) {
                $freddy['position'] = min(10, $freddy['position'] + 2);
                $freddy['last_moved'] = $state['time'];

                if ($freddy['position'] >= 8 && !$freddy['laugh_played']) {
                    $state['audio_playing'] = 'freddy_laugh';
                    $freddy['laugh_played'] = true;
                }
            }

            // Freddy can hide in the shadows
            if (rand(1, 100) < (15 * $current_night)) {
                $freddy['is_hidden'] = true;
            }
        }
    }

    // Bonnie's AI - prefers left side and moves frequently
    if (isset($state['animatronics']['bonnie']) && is_array($state['animatronics']['bonnie'])) {
        $bonnie = &$state['animatronics']['bonnie'];
        if (rand(1, 100) < (15 + ($current_night * 3) - $bonnie['last_moved'])) {
            $bonnie['position'] = min(10, $bonnie['position'] + 1);
            $bonnie['last_moved'] = $state['time'];

            if ($bonnie['position'] > 7 && !$state['left_door_closed']) {
                $state['audio_playing'] = 'banging';
            }
        }
    }

    // Chica's AI - prefers right side and kitchen
    if (isset($state['animatronics']['chica']) && is_array($state['animatronics']['chica'])) {
        $chica = &$state['animatronics']['chica'];
        if (rand(1, 100) < (12 + ($current_night * 2) - $chica['last_moved'])) {
            // Sometimes Chica goes to the kitchen first
            if ($chica['position'] < 3 && rand(1, 100) < 30) {
                $chica['in_kitchen'] = true;
            } else {
                $chica['position'] = min(10, $chica['position'] + 1);
                $chica['last_moved'] = $state['time'];
                $chica['in_kitchen'] = false;

                if ($chica['position'] > 7 && !$state['right_door_closed']) {
                    $state['audio_playing'] = 'banging';
                }
            }
        }
    }

    // Foxy's AI - stays in cove but can run if not monitored
    if (isset($state['animatronics']['foxy']) && is_array($state['animatronics']['foxy']) && isset($difficulty_settings[$current_night]['foxy_active']) && $difficulty_settings[$current_night]['foxy_active']) {
        $foxy = &$state['animatronics']['foxy'];
        if ($foxy['run_cooldown'] > 0) {
            $foxy['run_cooldown']--;
        } else {
            // Increase Foxy's stage if not being watched
            if ($state['cameras'] != 'pirate_cove' && rand(1, 100) < (10 + ($current_night * 5))) {
                $foxy['stage'] = min(4, $foxy['stage'] + 1);

                if ($foxy['stage'] == 4) {
                    // Foxy runs!
                    $foxy['position'] = 8;
                    $state['audio_playing'] = 'foxy_running';
                    $foxy['run_cooldown'] = 5;
                } elseif ($foxy['stage'] == 2 || $foxy['stage'] == 3) {
                    $state['audio_playing'] = 'foxy_knock';
                }
            }
        }
    }
}

function checkGameOverConditions()
{
    global $state;

    // Power ran out
    if ($state['power'] <= 0) {
        $state['game_over'] = true;
        $state['win'] = false;
        $state['message'] = "Power ran out! Game Over!";
        $state['audio_playing'] = 'power_out';
        return;
    }

    // Animatronics got you
    foreach ($state['animatronics'] as $name => $data) {
        if (isset($data['position']) && $data['position'] >= 10) {
            $state['game_over'] = true;
            $state['win'] = false;
            $state['message'] = ucfirst($name) . " got you! Game Over!";
            $state['audio_playing'] = 'jumpscare_' . $name;
            return;
        }
    }

    // Special case for Foxy - if he runs and door isn't closed
    if (isset($state['animatronics']['foxy']) && $state['animatronics']['foxy']['stage'] == 4 && !$state['left_door_closed']) {
        $state['game_over'] = true;
        $state['win'] = false;
        $state['message'] = "Foxy got you! Game Over!";
        $state['audio_playing'] = 'jumpscare_foxy';
        return;
    }
}

function resetForNewNight()
{
    global $state;

    $state['time'] = 0;
    $state['power'] = 100;
    $state['cameras'] = 'office';
    $state['left_door_closed'] = false;
    $state['right_door_closed'] = false;
    $state['game_over'] = false;
    $state['win'] = false;
    $state['audio_playing'] = '';

    // Reset animatronics but increase their base aggression
    foreach ($state['animatronics'] as &$animatronic) {
        if (is_array($animatronic)) {
            $animatronic['position'] = 0;
            $animatronic['aggression'] = $state['night'] * 0.8;
            $animatronic['last_moved'] = 0;

            // Character-specific resets
            if (isset($animatronic['is_hidden'])) $animatronic['is_hidden'] = false;
            if (isset($animatronic['laugh_played'])) $animatronic['laugh_played'] = false;
            if (isset($animatronic['in_kitchen'])) $animatronic['in_kitchen'] = false;
            if (isset($animatronic['run_cooldown'])) $animatronic['run_cooldown'] = 0;
            if (isset($animatronic['stage'])) $animatronic['stage'] = 1;
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>PHP FNAF - Night <?= $state['night'] ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #000;
            color: #fff;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .game-container {
            width: 800px;
            background: #222;
            border: 5px solid #444;
            padding: 20px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.8);
        }

        h1,
        h2,
        h3,
        p {
            text-align: center;
            margin-bottom: 10px;
        }

        .office {
            background: #333;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
            height: 300px;
            overflow: hidden;
            /* To contain the doors */
            border-radius: 8px;
        }

        .camera-view {
            background: #111;
            padding: 20px;
            display: none;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .controls {
            margin-top: 20px;
            text-align: center;
        }

        button {
            padding: 10px 20px;
            margin: 5px;
            background: #555;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-size: 16px;
            transition: background 0.3s ease;
        }

        button:hover {
            background: #777;
        }

        button:disabled {
            background: #333;
            color: #666;
            cursor: not-allowed;
        }

        .game-over,
        .win {
            color: #f00;
            font-size: 24px;
            text-align: center;
            margin: 20px 0;
            font-weight: bold;
        }

        .win {
            color: #0f0;
        }

        .power-meter {
            height: 20px;
            background: #333;
            margin: 10px 0;
            border-radius: 5px;
            overflow: hidden;
        }

        .power-level {
            height: 100%;
            background: #0f0;
            width: <?= $state['power'] ?>%;
            transition: width 0.3s ease-in border-radius: 5px;
        }

        .door {
            position: absolute;
            bottom: 0;
            width: 100px;
            height: 180px;
            background: #555;
            transition: background 0.3s ease;
            box-shadow: inset -5px 0 10px rgba(0, 0, 0, 0.5), inset 5px 0 10px rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            color: #eee;
            font-size: 1.2em;
            user-select: none;
            /* Prevent text selection */
        }

        .door.left {
            left: 20px;
            border-right: 3px solid #333;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .door.right {
            right: 20px;
            border-left: 3px solid #333;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }

        .door.closed {
            background: #722;
            color: #fff;
        }

        .message {
            color: #ff0;
            margin: 10px 0;
            min-height: 20px;
            font-weight: bold;
            text-align: center;
        }

        .debug-info {
            margin-top: 20px;
            font-size: 12px;
            color: #888;
            padding: 10px;
            background: #333;
            border-radius: 5px;
        }

        .character-status {
            margin: 10px 0;
            padding: 15px;
            background: #222;
            border-radius: 8px;
        }

        .character-name {
            font-weight: bold;
            color: #ff0;
        }

        .time-display {
            font-size: 28px;
            margin: 15px 0;
            text-align: center;
            letter-spacing: 2px;
        }

        .camera-feed {
            background: #000;
            border: 2px solid #444;
            padding: 15px;
            margin: 10px 0;
            min-height: 150px;
            border-radius: 5px;
            white-space: pre-line;
            /* Preserve line breaks */
        }

        .foxy-stage {
            display: inline-block;
            width: 20px;
            height: 20px;
            background: #f00;
            margin: 0 2px;
            border-radius: 50%;
            vertical-align: middle;
        }

        .foxy-stage.active {
            background: #ff0;
        }
    </style>
    <script>
        // Audio simulation (would be actual audio files in a real implementation)
        function playAudio(type) {
            console.log("Playing audio:", type);
            // In a real implementation, this would play actual sound files
        }

        // Check if we need to play audio when page loads
        window.onload = function() {
            <?php if (!empty($state['audio_playing'])): ?>
                playAudio('<?= $state['audio_playing'] ?>');
            <?php endif; ?>
        };
    </script>
</head>

<body>
    <div class="game-container">
        <h1>Five Nights at Freddy's (PHP Version)</h1>
        <h2>Night <?= $state['night'] ?> of <?= $state['max_nights'] ?></h2>

        <?php if ($state['game_over']): ?>
            <div class="<?= $state['win'] ? 'win' : 'game-over' ?>">
                <?= $state['message'] ?>
                <?php if ($state['win'] && $state['night'] < $state['max_nights']): ?>
                    <button onclick="location.href='?action=next_night'">Continue to Night <?= $state['night'] + 1 ?></button>
                <?php else: ?>
                    <button onclick="location.href='?action=restart'">Play Again</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="time-display">12:0<?= $state['time'] < 10 ? '0' . $state['time'] : $state['time'] ?> AM</div>
            <div class="power-meter">
                <div class="power-level"></div>
            </div>
            <p style="text-align: center;">Power: <?= $state['power'] ?>%</p>

            <div class="message"><?= $state['message'] ?></div>

            <div class="office" style="<?= $state['cameras'] != 'office' ? 'display:none;' : '' ?>">
                <h3>Security Office</h3>
                <p style="text-align: center;">Monitor the pizzeria and survive until 6 AM.</p>

                <div class="door left <?= $state['left_door_closed'] ? 'closed' : '' ?>">Left Door</div>
                <div class="door right <?= $state['right_door_closed'] ? 'closed' : '' ?>">Right Door</div>

                <?php
                if (($state['animatronics']['bonnie']['position'] > 7 ||
                        (isset($state['animatronics']['foxy']) && $state['animatronics']['foxy']['position'] > 7)) &&
                    !$state['left_door_closed']
                ) {
                    echo "<p style='color:red; text-align: center; font-weight: bold;'>Warning! Animatronic near the left door!</p>";
                }
                if ($state['animatronics']['chica']['position'] > 7 && !$state['right_door_closed']) {
                    echo "<p style='color:red; text-align: center; font-weight: bold;'>Warning! Animatronic near the right door!</p>";
                }
                if ($state['animatronics']['freddy']['position'] > 7) {
                    echo "<p style='color:red; text-align: center; font-weight: bold;'>You hear distant laughter...</p>";
                }
                ?>

                <div class="controls">
                    <button onclick="location.href='?action=use_camera&location=show_stage'">Show Stage</button>
                    <button onclick="location.href='?action=use_camera&location=pirate_cove'" <?= !$difficulty_settings[$state['night']]['foxy_active'] ? 'disabled' : '' ?>>Pirate Cove</button>
                    <button onclick="location.href='?action=use_camera&location=kitchen'">Kitchen (Audio Only)</button>
                    <button onclick="location.href='?action=toggle_left_door'">
                        <?= $state['left_door_closed'] ? 'Open Left Door' : 'Close Left Door' ?>
                    </button>
                    <button onclick="location.href='?action=toggle_right_door'">
                        <?= $state['right_door_closed'] ? 'Open Right Door' : 'Close Right Door' ?>
                    </button>
                    <button onclick="location.href='?action=next_hour'">Next Hour</button>
                </div>
            </div>

            <div class="camera-view" style="<?= $state['cameras'] == 'show_stage' ? 'display:block;' : '' ?>">
                <h3>Show Stage Camera</h3>
                <div class="camera-feed">
                    <?php
                    $freddy = $state['animatronics']['freddy'];
                    $bonnie = $state['animatronics']['bonnie'];
                    $chica = $state['animatronics']['chica'];

                    $output = "";
                    if ($freddy['is_hidden']) {
                        $output .= "Freddy is not visible (hidden in shadows)\n";
                    } elseif ($freddy['position'] < 3) {
                        $output .= "Freddy is on stage.\n";
                    } elseif ($freddy['position'] < 6) {
                        $output .= "Freddy has left the stage.\n";
                    } else {
                        $output .= "Freddy is somewhere else in the building.\n";
                    }

                    if ($bonnie['position'] < 3) {
                        $output .= "Bonnie is on stage.\n";
                    } elseif ($bonnie['position'] < 6) {
                        $output .= "Bonnie has left the stage.\n";
                    } elseif ($bonnie['position'] >= 6 && $bonnie['position'] < 8) {
                        $output .= "Bonnie is in the hallways.\n";
                    } else {
                        $output .= "Bonnie is near the office.\n";
                    }

                    if ($chica['in_kitchen']) {
                        $output .= "Chica is in the kitchen (no video).\n";
                    } elseif ($chica['position'] < 3) {
                        $output .= "Chica is on stage.\n";
                    } elseif ($chica['position'] < 6) {
                        $output .= "Chica has left the stage.\n";
                    } elseif ($chica['position'] >= 6 && $chica['position'] < 8) {
                        $output .= "Chica is in the hallways.\n";
                    } else {
                        $output .= "Chica is near the office.\n";
                    }
                    echo nl2br(htmlspecialchars($output));
                    ?>
                </div>
                <button onclick="location.href='?action=close_camera'">Close Camera</button>
            </div>

            <div class="camera-view" style="<?= $state['cameras'] == 'pirate_cove' ? 'display:block;' : '' ?>">
                <h3>Pirate Cove Camera</h3>
                <div class="camera-feed">
                    <?php
                    if (!$difficulty_settings[$state['night']]['foxy_active']) {
                        echo "<p>Pirate Cove is inactive tonight.</p>";
                    } else {
                        $foxy = $state['animatronics']['foxy'];
                        echo "<p>Foxy: ";
                        for ($i = 1; $i <= 4; $i++) {
                            echo "<span class='foxy-stage " . ($i <= $foxy['stage'] ? 'active' : '') . "'></span>";
                        }
                        echo "</p>";

                        if ($foxy['stage'] == 1) {
                            echo "<p>Foxy is in Pirate Cove, behind the curtain.</p>";
                        } elseif ($foxy['stage'] == 2) {
                            echo "<p>Foxy is peeking out of the curtain.</p>";
                        } elseif ($foxy['stage'] == 3) {
                            echo "<p>Foxy is ready to make a run for it!</p>";
                        } else {
                            echo "<p>Foxy has left Pirate Cove!</p>";
                        }
                    }
                    ?>
                </div>
                <button onclick="location.href='?action=close_camera'">Close Camera</button>
            </div>

            <div class="camera-view" style="<?= $state['cameras'] == 'kitchen' ? 'display:block;' : '' ?>">
                <h3>Kitchen Camera (Audio Feed Only)</h3>
                <div class="camera-feed">
                    <?php
                    if ($state['animatronics']['chica']['in_kitchen']) {
                        echo "<p>You hear loud clanging of pots and pans.</p>";
                    } else {
                        echo "<p>The kitchen is silent.</p>";
                    }
                    ?>
                </div>
                <button onclick="location.href='?action=close_camera'">Close Camera</button>
            </div>

            <div class="character-status">
                <h3>Animatronic Status</h3>
                <?php foreach ($state['animatronics'] as $name => $data): ?>
                    <div>
                        <span class="character-name"><?= ucfirst($name) ?>:</span>
                        <?php if ($name == 'foxy' && $difficulty_settings[$state['night']]['foxy_active']): ?>
                            Stage <?= $data['stage'] ?>/4
                            <?php if ($data['run_cooldown'] > 0): ?>
                                (cooldown: <?= $data['run_cooldown'] ?>)
                            <?php endif; ?>
                        <?php else: ?>
                            Position <?= $data['position'] ?>/10
                        <?php endif; ?>
                        <?php if (isset($data['is_hidden']) && $data['is_hidden']): ?>
                            (Hidden)
                        <?php endif; ?>
                        <?php if (isset($data['in_kitchen']) && $data['in_kitchen']): ?>
                            (In Kitchen)
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="debug-info">
                <h4>Debug Info</h4>
                <p>Current night difficulty: <?= $state['night'] ?></p>
                <p>Power: <?= $state['power'] ?></p>
                <p>Time: 12:0<?= $state['time'] ?></p>
                <p>Power drain: <?= $difficulty_settings[$state['night']]['power_drain'] ?? 'N/A' ?>% per hour</p>
                <p>Move chance base: <?= $difficulty_settings[$state['night']]['move_chance'] ?? 'N/A' ?></p>
                <p>Foxy active: <?= ($difficulty_settings[$state['night']]['foxy_active'] ?? false) ? 'Yes' : 'No' ?></p>
                <p>Cameras active: <?= $state['cameras'] ?></p>
                <p>Left door closed: <?= $state['left_door_closed'] ? 'Yes' : 'No' ?></p>
                <p>Right door closed: <?= $state['right_door_closed'] ? 'Yes' : 'No' ?></p>
                <?php foreach ($state['animatronics'] as $name => $data): ?>
                    <p><?= ucfirst($name) ?> Position: <?= $data['position'] ?? 'N/A' ?>, Last Moved: <?= $data['last_moved'] ?? 'N/A' ?><?php if (isset($data['stage'])): ?>, Stage: <?= $data['stage'] ?><?php endif; ?></p>
                <?php endforeach; ?>
                <p>Audio playing: <?= $state['audio_playing'] ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>