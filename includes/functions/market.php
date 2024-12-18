<?php

function meiko_market_table_shortcode() {
    global $wpdb;

    $items = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}meiko_market_items");
    
    $output = '<div>
    <select id="item-filter" style="padding: 5px; margin-right: 10px;">
        <option value="all">All Items</option>
        <option value="food">Food</option>
        <option value="stocks">Stocks</option>
        <option value="normal">Normal Items</option>
    </select>
    <button id="filter-button" style="padding: 5px 10px; margin-bottom: 5px; background-color: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
        Filter
    </button>
    </div>';

    $output .= '<table id="market-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Price</th>
                <th>Statistics</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($items as $item) {
        $output .= '<tr class="market-item" data-type="' . esc_attr($item->type) . '">
            <td>' . esc_html($item->name) . '</td>
            <td>Money: ' . esc_html($item->price) . '  Moves: ' . esc_html($item->moves_price) . '</td>';
        
        if ($item->type === 'stocks') {
            $output .= '<td>Stock - Current Price: ' . esc_html($item->current_price) . '</td>';
        } elseif ($item->type === 'food') {
            $output .= '<td>Food item</td>';
        } else {
            $output .= '<td>Defense: ' . esc_html($item->defense) . '  Attack: ' . esc_html($item->attack) . '</td>';
        }
        
        $output .= '<td>
            <input type="number" name="quantity" value="1" min="1" class="meiko-item-quantity">
            <button class="meiko-buy-item" data-item-id="' . esc_attr($item->id) . '">Buy</button>';
        
        if ($item->type === 'stocks') {
            $output .= ' <button class="meiko-sell-item" data-item-id="' . esc_attr($item->id) . '">Sell</button>';
        }
        
        $output .= '</td></tr>';
    }

    $nonce = wp_create_nonce('meiko_buy_nonce');
    $output .= '</tbody></table>';
    $output .= '<script>var meikoBuyNonce = "' . $nonce . '";</script>';

    return $output;
}

function meiko_buy_item_callback() {
    global $wpdb;
    
    check_ajax_referer('meiko_buy_nonce', 'nonce');
    
    $item_id = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    $user_id = get_current_user_id();

    $player_table = $wpdb->prefix . "mk_players";
    $player = $wpdb->get_row($wpdb->prepare("SELECT * FROM $player_table WHERE user_id = %d", $user_id));

    // Fetch item data from the custom table
    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}meiko_market_items WHERE id = %d", $item_id));

    if (!$item) {
        wp_send_json_error(array('message' => 'Invalid item.'));
        return;
    }

    // Buying logic for stock items
    if ($item->type === "stocks") {
        if ($player->money >= $item->current_price * $quantity) {
            $money_left = $player->money - ($item->current_price * $quantity);
            
            // Deduct moves
            $new_moves_total = $player->moves - ($item->moves_price * $quantity);
    
            // Check if buying stocks would reduce the player's moves to less than 0
            if ($new_moves_total < 0) {
                wp_send_json_error(array('message' => 'Not enough moves.'));
                return;
            }
    
            // Deduct money and add stock to player's inventory
            $wpdb->update($player_table, array(
                'money' => $money_left
            ), array('user_id' => $user_id));

            // Check if the player has already bought this stock previously
            $existing_stock = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mk_player_items WHERE player_id = %d AND item_id = %d", $player->id, $item_id));

            if ($existing_stock) {
                // Update quantity for existing record
                $new_quantity = $existing_stock->quantity + $quantity;
                $wpdb->update($wpdb->prefix . "mk_player_items", array('quantity' => $new_quantity), array('player_id' => $player->id, 'item_id' => $item_id));
            } else {
                // Insert new record
                $wpdb->insert($wpdb->prefix . "mk_player_items", array(
                    'player_id' => $player->id,
                    'item_id' => $item_id,
                    'quantity' => $quantity
                ));
            }
            // Deduct moves
            $wpdb->update($player_table, array(
                'moves' => $new_moves_total
            ), array('user_id' => $user_id));


            wp_send_json_success(array('message' => 'Stock purchased successfully!'));

        } else {
            wp_send_json_error(array('message' => 'Not enough money to purchase this stock.'));
        }

    } else {
        // Existing logic for food and normal items
        
        $item_price = $item->price * $quantity;
        $item_moves_price = $item->moves_price * $quantity;

        if ($player->money >= $item_price && $player->moves >= $item_moves_price) {
            $money_left = $player->money - $item_price;
            $moves_left = $player->moves - $item_moves_price;

            if ($item->type === "food") {
                // Logic for purchasing food items
                $wpdb->update($player_table, array(
                    'money' => $money_left,
                    'moves' => $moves_left,
                    'food'  => $player->food + $quantity
                ), array('user_id' => $user_id));

                wp_send_json_success(array('message' => 'Food purchased successfully!'));

            } else {
                // Logic for purchasing normal items
                $item_defense = $item->defense * $quantity;
                $item_attack = $item->attack * $quantity;
                
                $wpdb->update($player_table, array(
                    'money' => $money_left,
                    'moves' => $moves_left,
                    'defense' => $player->defense + $item_defense,
                    'attack' => $player->attack + $item_attack
                ), array('user_id' => $user_id));

                // Check if the player has already bought this item previously
                $existing_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mk_player_items WHERE player_id = %d AND item_id = %d", $player->id, $item_id));

                if ($existing_item) {
                    // Update quantity for existing record
                    $new_quantity = $existing_item->quantity + $quantity;
                    $wpdb->update($wpdb->prefix . "mk_player_items", array('quantity' => $new_quantity), array('player_id' => $player->id, 'item_id' => $item_id));
                } else {
                    // Insert new record
                    $wpdb->insert($wpdb->prefix . "mk_player_items", array(
                        'player_id' => $player->id,
                        'item_id' => $item_id,
                        'quantity' => $quantity
                    ));
                }

                wp_send_json_success(array('message' => 'Item purchased successfully!'));
            }
        } else {
            wp_send_json_error(array('message' => 'Not enough resources to purchase this item.'));
        }
    }
}


function meiko_update_stock_prices() {
    global $wpdb;

    // Fetch all stocks
    $stocks = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}meiko_market_items WHERE type = 'stocks'");

    foreach ($stocks as $stock) {
        // Calculate new price based on a random percentage in the range of -20% to +20%
        $percentage = mt_rand(-20, 20) / 100;
        $price_change = $stock->price * $percentage;
        $new_price = $stock->price + $price_change;

        $wpdb->update(
            "{$wpdb->prefix}meiko_market_items",
            array('current_price' => $new_price),
            array('id' => $stock->id)
        );
    }

    wp_send_json_success(array('message' => 'Stock prices updated!'));
}

function meiko_sell_stock_callback() {
    global $wpdb;

    check_ajax_referer('meiko_sell_nonce', 'nonce'); // Assuming you'll have a separate nonce for selling

    $item_id = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    $user_id = get_current_user_id();

    $player_table = $wpdb->prefix . "mk_players";
    $player = $wpdb->get_row($wpdb->prepare("SELECT * FROM $player_table WHERE user_id = %d", $user_id));

    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}meiko_market_items WHERE id = %d", $item_id));
    if (!$item || $item->type !== "stocks") {
        wp_send_json_error(array('message' => 'Invalid stock item.'));
        return;
    }

    // Check if player owns the stock
    $owned_stock = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mk_player_items WHERE player_id = %d AND item_id = %d", $player->id, $item_id));

    if (!$owned_stock || $owned_stock->quantity < $quantity) {
        wp_send_json_error(array('message' => 'You do not own enough of this stock to sell.'));
        return;
    }

    // Calculate money after selling
    $money_earned = $item->current_price * $quantity;
    $new_money_total = $player->money + $money_earned;

    // Update player's money
    $wpdb->update($player_table, array(
        'money' => $new_money_total
    ), array('user_id' => $user_id));
    if ($wpdb->last_error) {
        wp_send_json_error(array('message' => 'Database Error: ' . $wpdb->last_error));
    }
    // Update (or delete) the stock quantity the player owns
    if ($owned_stock->quantity == $quantity) {
        // Delete record if selling all stocks
        $wpdb->delete($wpdb->prefix . "mk_player_items", array('player_id' => $player->id, 'item_id' => $item_id));
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => 'Database Error: ' . $wpdb->last_error));
        }
    } else {
        $new_quantity = $owned_stock->quantity - $quantity;
        $wpdb->update($wpdb->prefix . "mk_player_items", array('quantity' => $new_quantity), array('player_id' => $player->id, 'item_id' => $item_id));
        if ($wpdb->last_error) {
            wp_send_json_error(array('message' => 'Database Error: ' . $wpdb->last_error));
        }
    }

    // Add moves
    $new_moves_total = $player->moves + ($item->moves_price * $quantity);
    $wpdb->update($player_table, array(
        'moves' => $new_moves_total
    ), array('user_id' => $user_id));

    wp_send_json_success(array('message' => 'Stock sold successfully!'));
}
?>