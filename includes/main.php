<!-- MAIN -->
<main>
  <section class="hero">
    <div class="hero-card">
      <div class="hero-copy">
        <h1>Find Places to Visit Near You</h1>
        <p class="hero-subtitle">Enter your postcode to discover attractions ordered by distance.</p>
      </div>

      <form id="postcodeSearchForm" class="search-card" onsubmit="searchNearby(event)">
        <label for="postcodeInput" class="visually-hidden">Postcode</label>
        <input id="postcodeInput" class="postcode-input" type="search" placeholder="Enter postcode (e.g. EH1 1JJ)" autocomplete="postal-code" />
        <button id="searchButton" type="submit" class="search-button">Search</button>
      </form>
    </div>
  </section>

  <section class="results-section">
    <div id="resultsHeader" class="results-header hidden">
      <p>Results near <strong id="searchPostcode"></strong></p>
    </div>

    <div id="resultsContainer" class="results-grid" role="status" aria-live="polite">
      <div class="result-message">Enter a UK postcode to find attractions nearby.</div>
    </div>
  </section>
</main>

