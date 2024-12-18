<?php
function meiko_friends_leaderboard_shortcode() {
    global $wpdb;
    $current_user_id = get_current_user_id();
    
    // Fetch the friend IDs from the database
    $friends = $wpdb->get_var($wpdb->prepare("SELECT friend_ids FROM {$wpdb->prefix}mk_players WHERE id = %d", $current_user_id));
    
    // Ensure $friends is not null
    if ($friends === null) {
        $friends = '';
    }
    
    // Split the friend IDs into an array
    $friend_ids = explode(',', $friends);
    
    // Include the current user ID
    $friend_ids[] = $current_user_id;
    
    // Remove any empty values in case the string was empty
    $friend_ids = array_filter($friend_ids);
    
    // Convert the array to a comma-separated string for the SQL query
    if (empty($friend_ids)) {
        $friend_ids_str = '0'; // Use a dummy value to prevent SQL syntax errors
    } else {
        $friend_ids_str = implode(',', array_map('intval', $friend_ids));
    }

    // Query the database
    $leaderboard = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mk_players WHERE id IN ($friend_ids_str) ORDER BY score DESC");

    // Generate the output
    $output = '<table border="1" cellspacing="0" cellpadding="5">';
    $output .= '<thead><tr><th>Username</th><th>Score</th><th>Money</th><th>Rank</th><th>Faction</th></tr></thead><tbody>';
    foreach ($leaderboard as $player) {
        $output .= "<tr><td class='lb_username'>$player->username</td><td class='lb_score'>$player->score</td><td class='lb_money'>$player->money</td><td class='lb_rank'>$player->rank</td><td class='lb_faction'>$player->faction</td></tr>";
    }
    $output .= '</tbody></table>';

    return $output;
}
add_shortcode('meiko_friends_leaderboard', 'meiko_friends_leaderboard_shortcode');

function meiko_display_friend_requests_shortcode() {
    global $wpdb;
    $current_user_id = get_current_user_id();
    $requests = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mk_friend_requests WHERE receiver_id = %d", $current_user_id));

    if (isset($_POST['accept_request'])) {
        $sender_id = intval($_POST['sender_id']);
        
        // Add the sender to the current user's friends list
        $receiver_friends = $wpdb->get_var($wpdb->prepare("SELECT friend_ids FROM {$wpdb->prefix}mk_players WHERE id = %d", $current_user_id));
        $receiver_friends = $receiver_friends ? $receiver_friends . ",$sender_id" : "$sender_id";
        $wpdb->update("{$wpdb->prefix}mk_players", array('friend_ids' => $receiver_friends), array('id' => $current_user_id));
        
        // Add the current user to the sender's friends list
        $sender_friends = $wpdb->get_var($wpdb->prepare("SELECT friend_ids FROM {$wpdb->prefix}mk_players WHERE id = %d", $sender_id));
        $sender_friends = $sender_friends ? $sender_friends . ",$current_user_id" : "$current_user_id";
        $wpdb->update("{$wpdb->prefix}mk_players", array('friend_ids' => $sender_friends), array('id' => $sender_id));

        // Remove the friend request
        $wpdb->delete("{$wpdb->prefix}mk_friend_requests", array('id' => intval($_POST['request_id'])));
    } elseif (isset($_POST['reject_request'])) {
        // Remove the friend request
        $wpdb->delete("{$wpdb->prefix}mk_friend_requests", array('id' => intval($_POST['request_id'])));
    }

    // Generate the output
    $output = '<table border="1" cellspacing="0" cellpadding="5">';
    $output .= '<thead><tr><th>Sender</th><th>Sent Date</th><th>Actions</th></tr></thead><tbody>';
    foreach ($requests as $request) {
        $sender_name = $wpdb->get_var($wpdb->prepare("SELECT username FROM {$wpdb->prefix}mk_players WHERE id = %d", $request->sender_id));
        $output .= "<tr><td>$sender_name</td><td>$request->sent_date</td><td>
            <form class='meiko-friend-request-form' method='post'>
                <input type='hidden' name='request_id' value='$request->id'>
                <input type='hidden' name='sender_id' value='$request->sender_id'>
                <input type='submit' name='accept_request' value='Accept'>
                <input type='submit' name='reject_request' value='Reject'>
            </form>
        </td></tr>";
    }
    $output .= '</tbody></table>';

    return $output;
}

add_shortcode('meiko_display_friend_requests', 'meiko_display_friend_requests_shortcode');
?>
