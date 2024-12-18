let multiplier = 1.00;
let crashed = false;
let crashGameInterval;

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('start-crash').addEventListener('click', function() {
        this.disabled = true;
        crashed = false;
        placeBet(); // Place the bet, but do NOT start the game here
    });

    document.getElementById('cashout').addEventListener('click', function() {
        clearInterval(crashGameInterval);
        this.disabled = true;
        document.getElementById('start-crash').disabled = false;
        cashOut();
        resetGame();
    });

    document.getElementById('play_roulette_animation').addEventListener('click', playRouletteAnimation);

    // Randomly trigger crash event
    setInterval(() => {
        if (!crashed && multiplier > 1.00) {
            crashed = true;
            alert('Crashed!');
            document.getElementById('cashout').disabled = true;
            document.getElementById('start-crash').disabled = false;
            resetGame();
        }
    }, Math.floor(Math.random() * 36000) + 2000);
});

function resetGame() {
    multiplier = 1.00;
    document.getElementById('multiplier').innerText = '1.00x';
}

function placeBet() {
    let betAmount = document.getElementById('crash_bet').value;

    let xhr = new XMLHttpRequest();
    xhr.open('POST', meikoCasino.ajax_url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
            let response = JSON.parse(this.responseText);
            if (response.error) {
                alert(response.error);
                resetGame(); // Reset the game state
                document.getElementById('start-crash').disabled = false; // Reactivate the start button
                document.getElementById('cashout').disabled = true; // Ensure the cashout button remains disabled
            } else {
                // No error from the server, so start the game
                startGame();
            }
        }
    };

    xhr.send(`action=place_crash_bet&bet_amount=${betAmount}&casino_security=${meikoCasino.security}`);
}

function startGame() {
    crashGameInterval = setInterval(() => {
        if (crashed) {
            clearInterval(crashGameInterval);
        } else {
            multiplier += 0.01;
            document.getElementById('multiplier').innerText = multiplier.toFixed(2) + 'x';
        }
    }, 100);

    document.getElementById('cashout').disabled = false; // Enable the cashout button only when the game starts
}

function cashOut() {
    let xhr = new XMLHttpRequest();
    xhr.open('POST', meikoCasino.ajax_url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
            let response = JSON.parse(this.responseText);
            if (response.error) {
                alert(response.error);
            } else {
                alert("You cashed out. New balance: $" + response.new_balance);
            }
        } else if (this.readyState === 4) {
            alert("An error occurred while cashing out.");
        }
    };

    xhr.send(`action=cash_out_crash&multiplier=${multiplier}&casino_security=${meikoCasino.security}`);
}

function spinRoulette(resultNumber) {
    const wheel = document.getElementById('roulette-wheel');
    const numberWidth = document.querySelector('.roulette-number').offsetWidth;
    const totalNumbers = 49; // Number of unique positions in one cycle
    const translateXPosition = (100 / totalNumbers) * resultNumber + '%';

    wheel.style.transition = 'transform 3s ease-in-out';
    wheel.style.transform = `translateX(-${translateXPosition})`;

    setTimeout(() => {
        wheel.style.transition = 'none';
        wheel.style.transform = `translateX(0px)`;

        // Highlight the result number
        const sections = wheel.getElementsByClassName('roulette-number');
        for (const section of sections) {
            section.classList.remove('highlighted');
        }
        document.querySelector(`.roulette-number[data-number="${resultNumber}"]`).classList.add('highlighted');
    }, 3000);
}

function playRouletteAnimation() {
    const betAmount = parseFloat(document.getElementById('roulette_bet').value);
    const chosenColor = document.getElementById('color').value;

    // Validate inputs
    if (isNaN(betAmount) || betAmount <= 0 || !chosenColor) {
        alert('Invalid input. Please check your bet amount and chosen color.');
        return;
    }

    // Make AJAX request to get result from the server
    const data = {
        action: 'play_roulette_animation',
        bet_amount: betAmount,
        chosen_color: chosenColor,
        casino_security: meikoCasino.casino_nonce
    };

    jQuery.post(meikoCasino.ajax_url, data, function(response) {
        console.log('Server Response:', response); // Debugging line

        if (response.success) {
            const resultNumber = response.result_number;
            const resultColor = response.result_color;

            // Constants
            const totalNumbers = 49; // Number of unique positions
            const wheel = document.getElementById('roulette-wheel');
            const numberWidth = document.querySelector('.roulette-number').offsetWidth;

            // Debugging information
            console.log('Result Number:', resultNumber);

            // Try different offsets until you find the correct one
            const offset = 6; // Adjust this if necessary
            const adjustedResultIndex = (resultNumber - 1 - offset + totalNumbers) % totalNumbers; // 0-based index
            const moveDistance = -numberWidth * adjustedResultIndex; // Distance to move in pixels

            console.log('Adjusted Result Index:', adjustedResultIndex);
            console.log('Move Distance:', moveDistance);

            // Set up the animation
            wheel.style.transition = 'transform 3s ease-in-out';
            wheel.style.transform = `translateX(${moveDistance}px)`;

            // Handle the end of the animation
            setTimeout(() => {
                wheel.style.transition = 'none'; // Disable transition
                wheel.style.transform = `translateX(${moveDistance}px)`; // Final position

                // Highlight the result number
                const sections = wheel.getElementsByClassName('roulette-number');
                for (const section of sections) {
                    section.classList.remove('highlighted');
                }
                document.querySelector(`.roulette-number[data-number="${resultNumber}"]`).classList.add('highlighted');

                showPopup(`Result Number: ${resultNumber}, Result Color: ${resultColor}`);

                // Refresh the page after 1 second
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }, 3000); // Match the transition duration
        } else {
            alert('Something went wrong. Please try again.');
        }
    }, 'json'); // Ensure the response is parsed as JSON
}


function showPopup(message) {
    const popup = document.createElement('div');
    popup.className = 'popup';
    popup.innerHTML = `
        <div class="popup-content">
            <p>${message}</p>
            <button id="close-popup">Close</button>
        </div>
    `;
    document.body.appendChild(popup);

    document.getElementById('close-popup').addEventListener('click', function() {
        document.body.removeChild(popup);
    });
}
