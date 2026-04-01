

let selectedCards = [];
let pileIndex = 0;
let currentPile = [];
let autoSync = true;


function cardImage(card) {
    return `url('../cards/${card}.png')`;
}

function copyFullLink() {
    let fullUrl = window.location.href;
    navigator.clipboard.writeText(fullUrl)
        .then(() => alert("Copied: " + fullUrl))
        .catch(err => alert("Error copying: " + err));
}


function updatePileView(data) {
    const pile = document.getElementById("pile");

    if (currentPile.length === 0) {
        pile.innerHTML = "<p id='Empty'>Empty</p>";
        return;
    } else {
        pile.innerHTML = "";

    }


    if (pileIndex < 0) pileIndex = 0;
    if (pileIndex >= currentPile.length) pileIndex = currentPile.length - 1;

    let card = currentPile[pileIndex];
    let isTop = pileIndex === currentPile.length - 1;

    let pileCard = document.getElementById("pileCard");
    if (!pileCard) {
        pileCard = document.createElement("div");
        pileCard.id = "pileCard";
        pileCard.className = "pile-card";
        pile.appendChild(pileCard);
    }

    pileCard.style.backgroundImage = cardImage(card);
    pileCard.style.filter = isTop ? "none" : "grayscale(80%) brightness(0.7)";

    let backBtn = document.getElementById("pileBack");
    if (!backBtn) {
        backBtn = document.createElement("button");
        backBtn.id = "pileBack";
        backBtn.className = "pileBtn";
        backBtn.textContent = "⬅️";
        backBtn.onclick = () => {
            if (pileIndex > 0) {
                pileIndex--;
                autoSync = false;
                updatePileView();
            }
        };
        pile.appendChild(backBtn);
    }

    let nextBtn = document.getElementById("pileNext");
    if (!nextBtn) {
        nextBtn = document.createElement("button");
        nextBtn.id = "pileNext";
        nextBtn.className = "pileBtn";
        nextBtn.textContent = "➡️";
        nextBtn.onclick = () => {
            if (pileIndex < currentPile.length - 1) {
                autoSync = true;
                updatePileView();
            }
        };
        pile.appendChild(nextBtn);
    }

}



function refreshGame() {
    fetch("api.php?action=state&code=<?php echo $roomCode; ?>")
        .then(res => res.json())
        .then(data => {


            currentPile = data.pile || [];
            if (autoSync) pileIndex = currentPile.length - 1;
            updatePileView(data);


            let turn = document.getElementById("turn");
            turn.innerHTML = "<h3>Turn:</h3><p>" + data.turn + "</p>";


            let yourhand = document.getElementById("yourhand");
            yourhand.innerHTML = "";
            let you = "<?php echo $username; ?>";

            if (data.hands && data.hands[you]) {

                let hand = data.hands[you];


                hand.sort((a, b) => {
                    const rank = c => {
                        let r = c.replace(/[^0-9JQKA]/g, "");
                        return { "J": 11, "Q": 12, "K": 13, "A": 14 }[r] || parseInt(r);
                    };
                    return rank(a) - rank(b);
                });

                yourhand.innerHTML += "<h3>Your Hand:</h3>";

                if (hand.length > 0) {
                    hand.forEach(c => {
                        let btn = `
                                    <div class="card ${selectedCards.includes(c) ? 'selected' : ''}"
                                         style="background-image:${cardImage(c)}"
                                         onclick="toggleCard('${c}')">
                                    </div>`;
                        yourhand.innerHTML += btn;
                    });

                    if (selectedCards.length > 0) {
                        yourhand.innerHTML += `<br><button onclick="playSelected()">Play Selected</button>`;
                    }

                } else {
                    yourhand.innerHTML += "<p>(empty)</p>";
                }
            }


            let yourface = document.getElementById("yourface");
            yourface.innerHTML = "<h3>Your Face-Up:</h3>";
            if (data.faceup && data.faceup[you]) {
                let faceup = data.faceup[you];
                if (faceup.length > 0) {
                    faceup.forEach(c => {
                        if (data.hands[you].length === 0) {
                            yourface.innerHTML += `
                                <div class="card" style="background-image:${cardImage(c)}"
                                     onclick="playCard('${c}')"></div>
                                `;

                        } else {
                            yourface.innerHTML += `
                                <div class="card" style="background-image:${cardImage(c)}"></div>
                                `;

                        }
                    });
                } else {
                    yourface.innerHTML += "<p>(none)</p>";
                }
            }


            let yourbottom = document.getElementById("yourbottom");
            yourbottom.innerHTML = "<h3>Your Face-Down:</h3>";
            if (data.facedown && data.facedown[you]) {
                let facedown = data.facedown[you];
                if (facedown.length > 0) {
                    facedown.forEach(c => {
                        if (
                            data.hands[you].length === 0 &&
                            data.faceup[you].length === 0
                        ) {
                            yourbottom.innerHTML += `
                                <div class="card" style="background-image:url('../cards/back.png')"
                                     onclick="playCard('${c}')"></div>
                                `;

                        } else {
                            yourbottom.innerHTML += `
<div class="card" style="background-image:url('../cards/back.jpeg')"></div>
`;

                        }
                    });
                } else {
                    yourbottom.innerHTML += "<p>(none)</p>";
                }
            }


            let others = document.getElementById("others");
            others.innerHTML = "";



            Object.keys(data.hands).forEach(player => {
                if (player === you) return;

                let handSize = data.hands[player].length;
                let faceup = data.faceup[player] || [];
                let facedown = data.facedown[player] || [];


                if (handSize === 0 && faceup.length === 0 && facedown.length === 0) {
                    let wininglist = document.getElementById("wininglist");
                    wininglist.innerHTML += `
                                <div class="player-block">
                                    <h3>${player}</h3>
                                    <p><b>Status:</b> Out </p>
                                </div>
                            `;
                } else {
                    others.innerHTML += `
                                <div class="player-block">
                                    <h3>${player}</h3>
                                    <p><b>Cards in Hand:</b> ${handSize}</p>
                                    <p><b>Face-Up:</b> 
                                        ${faceup.length ? faceup.map(c => `[${c}]`).join(" ") : "(none)"}
                                    </p>
                                    <p><b>Face-Down:</b> ${facedown.length} hidden cards</p>
                                </div>
                            `;
                }
            });
        });
}


function toggleCard(card) {
    const idx = selectedCards.indexOf(card);
    if (idx === -1) selectedCards.push(card);
    else selectedCards.splice(idx, 1);
    refreshGame();
}

function playSelected() {
    if (selectedCards.length === 0) return;
    const params = new URLSearchParams();
    selectedCards.forEach(c => params.append('cards[]', c));
    fetch(`api.php?action=play&code=<?php echo $roomCode; ?>&${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            selectedCards = [];
            refreshGame();
        });
}

function startGame() {
    fetch("api.php?action=start&code=<?php echo $roomCode; ?>")
        .then(res => res.json())
        .then(refreshGame);
}

function playCard(card) {
    fetch("api.php?action=play&code=<?php echo $roomCode; ?>&cards[]=" + card)
        .then(res => res.json())
        .then(refreshGame);
}

setInterval(refreshGame, 1000);
window.onload = refreshGame;