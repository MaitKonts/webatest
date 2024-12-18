jQuery(document).ready(function($) {

    // Existing buy item functionality
    $('.meiko-buy-item').click(function(e) {
        e.preventDefault();
        var itemId = $(this).data('item-id');
        var quantity = $(this).siblings('.meiko-item-quantity').val();
        $.ajax({
            type: 'POST',
            url: MeikoAjax.ajax_url,
            data: {
                action: 'meiko_buy_item',
                nonce: MeikoAjax.buy_nonce,  // Notice the change here
                item_id: itemId,
                quantity: quantity
            },
            success: function(response) {
                alert(response.data.message);
            },
            error: function() {
                alert('There was an error processing your request.');
            }
        });
    });

    $('.meiko-plant-item form').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();

        $.post(MeikoAjax.ajax_url, formData, function(response) {
            alert(response.data.message);
        });
    });

    // New sell item functionality
    $('.meiko-sell-item').click(function(e) {
        e.preventDefault();
        var itemId = $(this).data('item-id');
        var quantity = $(this).siblings('.meiko-item-quantity').val();
        $.ajax({
            type: 'POST',
            url: MeikoAjax.ajax_url,
            data: {
                action: 'meiko_sell_stock',   // Different action for selling
                nonce: MeikoAjax.sell_nonce,  // Using the sell nonce
                item_id: itemId,
                quantity: quantity
            },
            success: function(response) {
                alert(response.data.message);
            },
            error: function() {
                alert('There was an error processing your request.');
            }
        });
    });

    $('.accept-fight-button').click(function() {
        var challengeId = $(this).data('challenge-id');

        $.ajax({
            type: 'POST',
            url: MeikoAjax.ajax_url,
            data: {
                action: 'mk_accept_fight',   // Different action for selling
                challenge_id: challengeId
            },
            success: function(response) {
                alert(response.data.message);
            },
            error: function() {
                alert('There was an error processing your request.');
            }
        });
    });

    jQuery(document).ready(function($){
        $('#upload-btn').click(function(e) {
            e.preventDefault();
            var image = wp.media({ 
                title: 'Upload Avatar',
                multiple: false
            }).open()
            .on('select', function(e){
                var uploaded_image = image.state().get('selection').first();
                var image_url = uploaded_image.toJSON().url;
                $('#avatar_url').val(image_url);
            });
        });
    });

});

document.addEventListener('DOMContentLoaded', function() {

    // Get all buy buttons in the token shop.
    const buyButtons = document.querySelectorAll('.meiko-buy-rank');
    
    buyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const rankName = button.getAttribute('data-rank-name'); // Changed from data-rank-id to data-rank-name
            
            // Check if rankName exists.
            if (rankName) {
                buyRank(rankName);
            } else {
                alert('Error: Invalid Rank.');
            }
        });
    });

    function buyRank(rankName) { // changed parameter from rankId to rankName
        const data = {
            'action': 'meiko_purchase_rank',
            'rank_name': rankName, // Updated to send rank_name
            'security': MeikoAjax.buy_nonce // assuming you've localized this object and nonce for security
        };

        fetch(MeikoAjax.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => {
            console.log(response); // Add this line to log the raw response.
            return response.json();
        })
        .then(data => {
            console.log(data);  // Add this line
            if (data.message) {
                alert(data.message);
            } else {
                console.log(data); // This can help in debugging if the server sends unexpected data.
            }
            if (data.success) {
                alert('Successfully purchased rank!');
                location.reload(); // or update the UI accordingly
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong. Please try again later.');
        });
    }

});

