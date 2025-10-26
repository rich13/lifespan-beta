@extends('layouts.app')

@section('page_title')
    Import Twitter Archive
@endsection

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="bi bi-twitter me-2"></i>Import Twitter Archive
                    </h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Import your Twitter archive as notes. Upload your tweets.js file and import tweets individually.
                    </p>

                    <!-- Upload Section -->
                    <div id="uploadSection" class="mb-4">
                        <h5>Step 1: Upload Your tweets.js File</h5>
                        <form id="uploadForm" class="mb-3">
                            <div class="mb-3">
                                <label for="tweetsFile" class="form-label">Select tweets.js</label>
                                <input type="file" class="form-control" id="tweetsFile" name="tweets_file" required>
                                <small class="form-text text-muted">Download your Twitter archive from your account settings and extract the tweets.js file from data/tweets.js</small>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload me-2"></i>Upload & Preview
                            </button>
                        </form>
                        <div id="uploadStatus"></div>
                    </div>

                    <!-- Tweets Display Section (hidden initially) -->
                    <div id="tweetsSection" class="mb-4 d-none">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Step 2: Import Tweets</h5>
                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted">
                                    Showing <span id="currentTweet">0</span> / <span id="totalTweets">0</span>
                                    | Imported: <span id="importedCount">0</span>
                                </small>
                                <div class="input-group input-group-sm" style="width: 120px;">
                                    <input type="number" class="form-control" id="jumpToInput" 
                                           min="1" placeholder="Jump to..." style="font-size: 0.875rem;">
                                    <button class="btn btn-outline-secondary" type="button" onclick="jumpToTweet()">
                                        <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="tweetCard" class="card mb-3" style="display: none;">
                            <div class="card-body">
                                <div class="d-flex gap-2 mb-3">
                                    <button type="button" class="btn btn-outline-secondary" id="backBtn" onclick="previousTweet()" style="display: none;">
                                        <i class="bi bi-chevron-left me-1"></i>Back
                                    </button>
                                    <button type="button" class="btn btn-primary flex-grow-1" id="importBtn" onclick="importCurrentTweet()">
                                        <i class="bi bi-download me-1"></i>Import This Tweet
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary flex-grow-1" id="skipBtn" onclick="skipTweet()">
                                        <i class="bi bi-skip-forward me-1"></i>Skip
                                    </button>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted d-block mb-2">
                                        <i class="bi bi-calendar me-1"></i>
                                        <span id="tweetDate"></span>
                                    </small>
                                    <p id="tweetText" class="card-text mb-0"></p>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="bi bi-heart-fill me-1" style="color: #e0245e;"></i>
                                        <span id="tweetLikes">0</span>
                                        <i class="bi bi-arrow-repeat me-1 ms-3" style="color: #17bf63;"></i>
                                        <span id="tweetRetweets">0</span>
                                    </small>
                                </div>

                                <div id="tweetStatus" class="mb-3"></div>
                            </div>
                        </div>

                        <div id="noMoreTweets" class="alert alert-success" style="display: none;">
                            <i class="bi bi-check-circle me-2"></i>
                            You've reviewed all tweets! 
                            <a href="{{ route('notes.index') }}" class="alert-link">View your notes</a>.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let allTweets = [];
let currentTweetIndex = 0;
let importedTweetIds = new Set();

// Load imported tweet IDs from localStorage
function loadImportedIds() {
    const stored = localStorage.getItem('twitter_imported_ids');
    if (stored) {
        importedTweetIds = new Set(JSON.parse(stored));
    }
    updateImportedCount();
}

// Save imported tweet IDs to localStorage
function saveImportedIds() {
    localStorage.setItem('twitter_imported_ids', JSON.stringify(Array.from(importedTweetIds)));
}

// Save current tweet position to localStorage
function saveCurrentPosition() {
    localStorage.setItem('twitter_current_index', currentTweetIndex.toString());
}

// Load current tweet position from localStorage
function loadCurrentPosition() {
    const stored = localStorage.getItem('twitter_current_index');
    if (stored) {
        currentTweetIndex = parseInt(stored);
    }
}

// Save tweets to localStorage
function saveTweets() {
    localStorage.setItem('twitter_all_tweets', JSON.stringify(allTweets));
}

// Load tweets from localStorage
function loadTweets() {
    const stored = localStorage.getItem('twitter_all_tweets');
    if (stored) {
        allTweets = JSON.parse(stored);
        return true;
    }
    return false;
}

// Update imported count display
function updateImportedCount() {
    document.getElementById('importedCount').textContent = importedTweetIds.size;
}

// Load and display the current tweet
function displayCurrentTweet() {
    if (currentTweetIndex >= allTweets.length) {
        document.getElementById('tweetCard').style.display = 'none';
        document.getElementById('noMoreTweets').style.display = 'block';
        return;
    }

    saveCurrentPosition(); // Save position whenever we display a tweet

    const tweet = allTweets[currentTweetIndex];
    const isImported = importedTweetIds.has(tweet.id_str);

    document.getElementById('currentTweet').textContent = currentTweetIndex + 1;
    document.getElementById('totalTweets').textContent = allTweets.length;
    document.getElementById('tweetDate').textContent = tweet.date;
    document.getElementById('tweetText').textContent = tweet.full_text;
    document.getElementById('tweetLikes').textContent = tweet.likes;
    document.getElementById('tweetRetweets').textContent = tweet.retweets;
    document.getElementById('tweetStatus').innerHTML = '';

    const importBtn = document.getElementById('importBtn');
    const skipBtn = document.getElementById('skipBtn');
    const backBtn = document.getElementById('backBtn');

    if (isImported) {
        importBtn.disabled = true;
        importBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Already Imported';
        skipBtn.textContent = 'Next';
        skipBtn.className = 'btn btn-primary flex-grow-1';
        backBtn.style.display = 'none'; // Hide back button if already imported
    } else {
        importBtn.disabled = false;
        importBtn.innerHTML = '<i class="bi bi-download me-1"></i>Import This Tweet';
        skipBtn.textContent = 'Skip';
        skipBtn.className = 'btn btn-outline-secondary flex-grow-1';
        backBtn.style.display = 'inline-block'; // Show back button if not imported
    }

    document.getElementById('tweetCard').style.display = 'block';
}

// Import the current tweet
async function importCurrentTweet() {
    const tweet = allTweets[currentTweetIndex];
    const importBtn = document.getElementById('importBtn');
    const statusDiv = document.getElementById('tweetStatus');

    importBtn.disabled = true;
    importBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Importing...';

    try {
        const response = await fetch('{{ route("settings.import.twitter.import-tweet") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ tweet: tweet })
        });

        let data;
        try {
            data = await response.json();
        } catch (jsonError) {
            const text = await response.text();
            console.error('JSON parse error:', jsonError, 'Response text:', text);
            throw new Error(`Server error: ${text}`);
        }

        if (data.success) {
            importedTweetIds.add(tweet.id_str);
            saveImportedIds();
            updateImportedCount();

            statusDiv.innerHTML = `
                <div class="alert alert-success small mb-0">
                    ✓ Imported as <a href="${data.note_url}" target="_blank" class="alert-link">${escapeHtml(data.note_name)}</a>
                </div>
            `;
            
            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Imported';

            // Auto-advance to next tweet after 1 second
            setTimeout(() => {
                currentTweetIndex++;
                displayCurrentTweet();
            }, 1000);
        } else {
            statusDiv.innerHTML = `<div class="alert alert-danger small mb-0">✗ ${data.message}</div>`;
            importBtn.disabled = false;
            importBtn.innerHTML = '<i class="bi bi-download me-1"></i>Retry';
        }
    } catch (error) {
        statusDiv.innerHTML = `<div class="alert alert-danger small mb-0">✗ Error: ${error.message}</div>`;
        importBtn.disabled = false;
        importBtn.innerHTML = '<i class="bi bi-download me-1"></i>Retry';
    }
}

// Skip to next tweet
function skipTweet() {
    currentTweetIndex++;
    displayCurrentTweet();
}

// Go back to the previous tweet
function previousTweet() {
    currentTweetIndex--;
    displayCurrentTweet();
}

// Jump to a specific tweet position
function jumpToTweet() {
    const input = document.getElementById('jumpToInput');
    const position = parseInt(input.value);
    
    if (!input.value || isNaN(position)) {
        alert('Please enter a valid tweet number');
        return;
    }
    
    if (position < 1 || position > allTweets.length) {
        alert(`Please enter a number between 1 and ${allTweets.length}`);
        return;
    }
    
    currentTweetIndex = position - 1; // Convert to 0-based index
    input.value = ''; // Clear input
    displayCurrentTweet();
}

// Handle file upload
document.getElementById('uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData();
    formData.append('tweets_file', document.getElementById('tweetsFile').files[0]);

    const statusDiv = document.getElementById('uploadStatus');
    statusDiv.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>Uploading and parsing...';

    try {
        const response = await fetch('{{ route("settings.import.twitter.upload") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            allTweets = data.all_tweets || [];
            currentTweetIndex = 0;
            loadImportedIds();
            loadCurrentPosition(); // Load current position after successful upload
            saveTweets(); // Save tweets to localStorage

            statusDiv.innerHTML = `<div class="alert alert-success">✓ Successfully parsed ${allTweets.length} tweets</div>`;

            // Show tweets section
            document.getElementById('uploadSection').classList.add('d-none');
            document.getElementById('tweetsSection').classList.remove('d-none');
            document.getElementById('noMoreTweets').style.display = 'none';

            displayCurrentTweet();
        } else {
            statusDiv.innerHTML = `<div class="alert alert-danger">✗ ${data.message}</div>`;
        }
    } catch (error) {
        statusDiv.innerHTML = `<div class="alert alert-danger">✗ Upload failed: ${error.message}</div>`;
    }
});

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Load imported IDs on page load
loadImportedIds();
loadCurrentPosition(); // Load current position on page load

// Try to load tweets from localStorage on page load
if (loadTweets() && allTweets.length > 0) {
    // Tweets were found in localStorage, show the tweets section
    document.getElementById('uploadSection').classList.add('d-none');
    document.getElementById('tweetsSection').classList.remove('d-none');
    document.getElementById('noMoreTweets').style.display = 'none';
    
    displayCurrentTweet();
}
</script>
@endsection
