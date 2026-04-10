const postcodeInput = document.getElementById('postcodeInput');
const resultsContainer = document.getElementById('resultsContainer');
const resultsHeader = document.getElementById('resultsHeader');
const resultsCount = document.getElementById('resultsCount');
const searchPostcode = document.getElementById('searchPostcode');
const searchButton = document.getElementById('searchButton');
const LAST_POSTCODE_KEY = 'exploreNearbyLastPostcode';

/**
 * Handle search form submission and fetch nearby attractions.
 * Uses the backend API to keep business logic in server-side PHP.
 */
async function searchNearby(event) {
  event.preventDefault();
  performSearch(postcodeInput.value.trim());
}

async function performSearch(postcode) {
  if (!postcode) {
    showMessage('Please enter a postcode before searching.');
    postcodeInput.focus();
    return;
  }

  toggleSearchButton(true);
  resultsHeader.classList.add('hidden');
  showLoading();

  try {
    const response = await fetch(`api/postcode.php?postcode=${encodeURIComponent(postcode)}`);
    const payload = await response.text();
    let data = null;

    try {
      data = JSON.parse(payload);
    } catch {
      // Invalid JSON from API
    }

    if (!response.ok) {
      showMessage((data && data.error) || 'Unable to fetch search results.', true);
      return;
    }

    if (!data || data.error) {
      showMessage(data?.error || 'Unable to fetch search results.', true);
      return;
    }

    if (!Array.isArray(data.results) || data.results.length === 0) {
      showMessage('No attractions were found near that postcode.');
      return;
    }

    searchPostcode.textContent = data.postcode;
    if (resultsCount) {
      resultsCount.textContent = `(${data.results.length} attractions found)`;
    }
    saveLastPostcode(data.postcode);
    resultsHeader.classList.remove('hidden');
    renderResults(data.results);
  } catch (error) {
    showMessage('Something went wrong while searching. Please try again.', true);
    // eslint-disable-next-line no-console
    console.error('Search error:', error);
  } finally {
    toggleSearchButton(false);
  }
}

function retrySearch() {
  performSearch(postcodeInput.value.trim());
}

function toggleSearchButton(isLoading) {
  if (!searchButton) {
    return;
  }

  searchButton.disabled = isLoading;
  searchButton.textContent = isLoading ? 'Searching…' : 'Search';
}

/**
 * Display a loading state while the API request is pending.
 */
function showLoading() {
  resultsContainer.innerHTML = '<div class="loading"><span class="loading-spinner" aria-hidden="true"></span>Searching attractions…</div>';
}

/**
 * Render an inline status message for the result area.
 */
function showMessage(message, showRetry = false) {
  resultsHeader.classList.add('hidden');
  resultsContainer.innerHTML = `
    <div class="result-message">
      <span>${escapeHtml(message)}</span>
      ${showRetry ? '<button type="button" class="retry-button">Try again</button>' : ''}
    </div>
  `;

  if (showRetry) {
    const retryButton = resultsContainer.querySelector('.retry-button');
    if (retryButton) {
      retryButton.addEventListener('click', retrySearch);
    }
  }
}

/**
 * Render returned attractions as accessible result cards.
 */
function renderResults(results) {
  resultsContainer.innerHTML = results
    .map((item) => {
      return `
        <article class="card">
          <div class="card-body">
            <h2 class="card-title">${escapeHtml(item.title)}</h2>
            <p class="card-description">${escapeHtml(item.description)}</p>
          </div>
          <div class="card-divider"></div>
          <div class="card-footer">
            <div class="card-details">
              <span class="tag">⏱️ ${formatDistance(item.distance)}</span>
              <span class="card-address">📍 ${escapeHtml(item.address)}</span>
            </div>
            <a class="trip-link" href="${escapeHtml(item.link)}" target="_blank" rel="noopener noreferrer">
              Trip Advisor
            </a>
          </div>
        </article>
      `;
    })
    .join('');
}

function formatDistance(distance) {
  return `${distance.toFixed(1)} miles away`;
}

function saveLastPostcode(postcode) {
  try {
    localStorage.setItem(LAST_POSTCODE_KEY, postcode);
  } catch {
    // Ignore storage errors in private mode.
  }
}

function restoreLastPostcode() {
  try {
    return localStorage.getItem(LAST_POSTCODE_KEY) || '';
  } catch {
    return '';
  }
}

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

document.addEventListener('DOMContentLoaded', () => {
  const lastPostcode = restoreLastPostcode();
  if (!lastPostcode) {
    return;
  }

  postcodeInput.value = lastPostcode;
  performSearch(lastPostcode);
});
