/**
 * EventStore.js
 * A centralized store for managing events, filters, and search state across Vue instances
 */
class EventStore {
  constructor() {
    // Initialize with defaults or from URL
    this.initializeFromUrl();
    
    // Add listeners collection
    this.listeners = [];
    
    // Track if we're currently updating to avoid loops
    this.isUpdating = false;
    
    // Track last fetch timestamp to prevent rapid consecutive calls
    this.lastFetchTimestamp = 0;
    
    // Listen for browser navigation (back/forward)
    window.addEventListener('popstate', () => {
      // Only reinitialize if we didn't cause this popstate
      if (!this.isUpdating) {
        this.initializeFromUrl();
        this.notify();
      }
    });
  }

  /**
   * Initialize store state from URL parameters
   */
  initializeFromUrl() {
    const params = new URLSearchParams(window.location.search);
    
    // Initialize state with defaults and URL values
    this.state = {
      location: {
        city: params.get('city') || null,
        lat: params.has('lat') ? parseFloat(params.get('lat')) : null,
        lng: params.has('lng') ? parseFloat(params.get('lng')) : null,
        searchType: params.get('searchType') || 'inPerson',
        live: params.get('live') === 'true'
      },
      dates: {
        start: params.get('start') || null,
        end: params.get('end') || null
      },
      filters: {
        categories: params.has('category') 
          ? params.get('category').split(',').map(Number) 
          : [],
        tags: params.has('tag') 
          ? params.get('tag').split(',').map(Number) 
          : [],
        price: [
          parseInt(params.get('price0') || 0),
          parseInt(params.get('price1') || 1000)
        ]
      },
      events: {
        data: [],
        total: 0,
        loading: false
      },
      // Keep track of the page for pagination
      page: params.get('page') ? parseInt(params.get('page')) : 1
    };
    
    // DEBUG: Log the searchType we're using
    console.log('EventStore initialized with searchType:', this.state.location.searchType);
  }

  /**
   * Update store state and synchronize URL
   * @param {Object} changes - Partial state updates
   * @param {boolean} fetchData - Whether to automatically fetch events after update
   */
  update(changes, fetchData = true) {
    if (this.isUpdating) return;
    this.isUpdating = true;
    
    // Deep merge changes with current state
    if (changes.location) {
      // IMPORTANT: Preserve searchType if not explicitly changed
      if (!changes.location.searchType) {
        changes.location.searchType = this.state.location.searchType || 'inPerson';
      }
      
      this.state.location = { ...this.state.location, ...changes.location };
      
      // DEBUG: Log when searchType changes
      console.log('Updated location with searchType:', this.state.location.searchType);
    }
    if (changes.dates) {
      this.state.dates = { ...this.state.dates, ...changes.dates };
    }
    if (changes.filters) {
      // Handle each filter type
      if (changes.filters.categories !== undefined) {
        this.state.filters.categories = changes.filters.categories;
      }
      if (changes.filters.tags !== undefined) {
        this.state.filters.tags = changes.filters.tags;
      }
      if (changes.filters.price !== undefined) {
        this.state.filters.price = changes.filters.price;
      }
    }
    if (changes.page !== undefined) {
      this.state.page = changes.page;
    }
    
    // Update URL to reflect new state
    this.updateUrl();
    
    // Notify subscribers
    this.notify();
    
    // If requested, fetch new event data
    if (fetchData) {
      this.fetchEvents();
    }
    
    // Reset updating flag after a small delay
    setTimeout(() => {
      this.isUpdating = false;
    }, 50);
  }

  /**
   * Clear date filters
   */
  clearDates() {
    this.update({
      dates: { start: null, end: null }
    }, true); // Fetch events after clearing dates
  }

  /**
   * Update URL based on current state
   */
  updateUrl() {
    const params = new URLSearchParams();
    
    // Add location parameters
    if (this.state.location.city) {
      params.set('city', this.state.location.city);
    }
    if (this.state.location.lat) {
      params.set('lat', this.state.location.lat.toString());
    }
    if (this.state.location.lng) {
      params.set('lng', this.state.location.lng.toString());
    }
    
    // CRITICAL FIX: Always include searchType in URL
    params.set('searchType', this.state.location.searchType || 'inPerson');
    
    // CRITICAL FIX: Always include 'live' parameter when searchType is inPerson
    // The backend checks for isset($request->live) to determine which view to render
    if (this.state.location.searchType === 'inPerson') {
      params.set('live', this.state.location.live ? 'true' : 'false');
    }
    
    // Add date parameters
    if (this.state.dates.start) {
      params.set('start', this.state.dates.start);
    }
    if (this.state.dates.end) {
      params.set('end', this.state.dates.end);
    }
    
    // Add filter parameters
    if (this.state.filters.categories.length > 0) {
      params.set('category', this.state.filters.categories.join(','));
    }
    if (this.state.filters.tags.length > 0) {
      params.set('tag', this.state.filters.tags.join(','));
    }
    
    // Add price parameters
    if (this.state.filters.price[0] > 0) {
      params.set('price0', this.state.filters.price[0].toString());
    }
    if (this.state.filters.price[1] < 1000) {
      params.set('price1', this.state.filters.price[1].toString());
    }
    
    // Add pagination
    if (this.state.page > 1) {
      params.set('page', this.state.page.toString());
    }
    
    // Update the URL
    const newUrl = `${window.location.pathname}?${params.toString()}`;
    window.history.replaceState(
      { source: 'eventStore' }, 
      document.title,
      newUrl
    );
  }

  /**
   * Fetch events from API based on current state
   * @returns {Promise} Promise that resolves with event data
   */
  fetchEvents() {
    // Avoid fetching too frequently
    const now = Date.now();
    if (now - this.lastFetchTimestamp < 200) {
      return Promise.resolve(this.state.events);
    }
    this.lastFetchTimestamp = now;
    
    // Set loading state
    this.state.events.loading = true;
    this.notify();
    
    // Build URL parameters from the current state
    const params = new URLSearchParams(window.location.search);
    
    // CRITICAL FIX: Make sure searchType is correct in API request
    params.set('searchType', this.state.location.searchType || 'inPerson');
    
    // Log the URL we're using
    console.log('Making API request with:', params.toString());
    
    // Make API request
    return fetch(`/api/index/search?${params.toString()}`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        // Update events in state
        this.state.events = {
          data: data.data || [],
          total: data.total || 0,
          loading: false,
          // Include other pagination properties from data
          current_page: data.current_page,
          per_page: data.per_page,
          from: data.from,
          to: data.to,
          last_page: data.last_page
        };
        
        // Notify subscribers
        this.notify();
        
        return this.state.events;
      })
      .catch(error => {
        console.error('Error fetching events:', error);
        this.state.events.loading = false;
        this.notify();
        throw error;
      });
  }

  /**
   * Subscribe to state changes
   * @param {Function} callback - Function to call when state changes
   * @returns {Function} Function to unsubscribe
   */
  subscribe(callback) {
    this.listeners.push(callback);
    
    // Call immediately with current state
    callback(this.state);
    
    // Return unsubscribe function
    return () => {
      this.listeners = this.listeners.filter(listener => listener !== callback);
    };
  }

  /**
   * Notify all subscribers of state changes
   */
  notify() {
    this.listeners.forEach(listener => {
      try {
        listener(this.state);
      } catch (e) {
        console.error('Error in EventStore listener:', e);
      }
    });
  }
  
  /**
   * Handle page change for pagination
   * @param {number} page - New page number
   */
  changePage(page) {
    this.update({ page }, true);
  }
  
  /**
   * Set the search type (inPerson or allEvents)
   * @param {string} searchType - The search type to set
   */
  setSearchType(searchType) {
    this.update({
      location: {
        searchType: searchType
      }
    }, true);
  }
  
  /**
   * Helper to debug the current state
   */
  debug() {
    console.log('EventStore Current State:', {
      location: this.state.location,
      searchType: this.state.location.searchType,
      dates: this.state.dates,
      filters: this.state.filters,
      currentURL: window.location.href
    });
  }
}

// Create singleton instance
window.eventStore = window.eventStore || new EventStore();

// Export the singleton
export default window.eventStore; 