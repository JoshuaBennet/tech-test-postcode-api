const postcodeInput = document.getElementById('postcodeInput');
const resultsContainer = document.getElementById('resultsContainer');
const resultsHeader = document.getElementById('resultsHeader');
const resultsCount = document.getElementById('resultsCount');
const searchPostcode = document.getElementById('searchPostcode');
const searchButton = document.getElementById('searchButton');

/**
 * Handle search form submission and fetch nearby attractions.
 * Uses the backend API to keep business logic in server-side PHP.
 */
async function searchNearby(event) {
  event.preventDefault();

  const postcode = postcodeInput.value.trim();

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
      showMessage((data && data.error) || 'Unable to fetch search results.');
      return;
    }

    if (!data || data.error) {
      showMessage(data?.error || 'Unable to fetch search results.');
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
    resultsHeader.classList.remove('hidden');
    renderResults(data.results);
  } catch (error) {
    showMessage('Something went wrong while searching. Please try again.');
    // eslint-disable-next-line no-console
    console.error('Search error:', error);
  } finally {
    toggleSearchButton(false);
  }
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
  resultsContainer.innerHTML = '<div class="loading">Searching attractions…</div>';
}

/**
 * Render an inline status message for the result area.
 */
function showMessage(message) {
  resultsHeader.classList.add('hidden');
  resultsContainer.innerHTML = `<div class="result-message">${escapeHtml(message)}</div>`;
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

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
