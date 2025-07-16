/**
 * Materiel State Management functionality
 * Handles the tabs, filtering, and state changes for materials
 */

document.addEventListener("DOMContentLoaded", function () {
  // Initialize the materiel tabs
  initMaterielStateTabs();

  // Set up event listeners for state change buttons
  setupStateChangeListeners();

  // Set up history button listeners
  setupHistoryButtonListeners();

  // Set up event listener for the state change form submission
  setupStateChangeFormSubmission();

  // Set up damage cause field toggle
  const stateSelect = document.getElementById("materiel-state-select");
  if (stateSelect) {
    stateSelect.addEventListener("change", function () {
      const damageContainer = document.getElementById("damage-cause-container");
      if (damageContainer) {
        if (this.value === "endommage" || this.value === "casse") {
          damageContainer.style.display = "block";
        } else {
          damageContainer.style.display = "none";
        }
      }
    });
  }
});

/**
 * Initialize the materiel state tabs functionality
 */
function initMaterielStateTabs() {
  const stateTabs = document.querySelectorAll(".materiel-state-tab");
  if (stateTabs) {
    stateTabs.forEach(tab => {
      tab.addEventListener("click", function () {
        // First, update active tab styling so the filter function knows which tab is active
        document.querySelectorAll(".materiel-state-tab").forEach(t => {
          t.classList.remove("active");
        });
        this.classList.add("active");

        // Now, filter the content based on the new active tab
        filterMaterielByState();

        // Finally, update the URL to reflect the current state
        const state = this.getAttribute("data-state");
        const url = new URL(window.location.href);
        url.searchParams.set("state", state);
        window.history.replaceState({}, "", url);
      });
    });

    // Initialize tab counts
    updateTabCounters();

    // Check if a specific state is in URL params
    const urlParams = new URLSearchParams(window.location.search);
    const stateParam = urlParams.get("state");

    let activeTab;

    // Set the appropriate tab as active
    if (stateParam) {
      // Try to find the tab with the state from URL
      const stateTab = document.querySelector(
        `.materiel-state-tab[data-state='${stateParam}']`
      );
      if (stateTab) {
        activeTab = stateTab;
      }
    }

    // If no active tab found from URL, set a default
    if (!activeTab) {
      activeTab =
        document.querySelector(".materiel-state-tab[data-state='all']") ||
        document.querySelector(
          ".materiel-state-tab[data-state='en-service']"
        ) ||
        (stateTabs.length > 0 ? stateTabs[0] : null);
    }

    // Directly activate the tab and filter
    if (activeTab) {
      activeTab.classList.add("active");
      filterMaterielByState(); // Filter based on the now-active tab
      console.log(
        "Materiel state tabs initialized. Active tab:",
        activeTab.getAttribute("data-state")
      );
    }
  }
}

/**
 * Filter materiel items based on their state
 * @param {string} state - The state to filter by ('en-service', 'en-stock', 'endommage', 'casse', 'all' for showing all)
 */
function filterMaterielByState() {
  // The filtering logic is now centralized in performSearch.
  // This function just needs to trigger it.
  if (typeof performSearch === "function") {
    performSearch("materiel");
  }

  // We still update the counters here after the filtering is done.
  updateTabCounters();
}

/**
 * Update the counters in each tab to reflect the current number of items in each state
 */
function updateTabCounters() {
  // Get all materiels
  const rows = document.querySelectorAll("#materiel-table tbody tr");

  // Count by state
  const counts = {
    "en-service": 0,
    "en-stock": 0,
    endommage: 0,
    casse: 0,
    all: 0,
  };

  // Count items by state
  rows.forEach(row => {
    const stateCell = row.querySelector(".materiel-state-value");
    if (stateCell) {
      const state = stateCell.getAttribute("data-state");
      console.log(`Counting material with state: ${state}`);
      if (counts.hasOwnProperty(state)) {
        counts[state]++;
      }
      counts.all++; // Always increment total count
    }
  });

  console.log("Tab counts:", counts);

  // Update badge counters if they exist
  Object.keys(counts).forEach(state => {
    const badge = document.querySelector(
      `.materiel-state-tab[data-state="${state}"] .badge`
    );
    if (badge) {
      badge.textContent = counts[state];
      console.log(`Updated tab ${state} badge to ${counts[state]}`);
    }
  });
}

/**
 * Set up event listeners for state change buttons
 */
function setupStateChangeListeners() {
  document.querySelectorAll(".state-change-btn").forEach(button => {
    button.addEventListener("click", function (e) {
      e.preventDefault();

      const numSerie = this.getAttribute("data-numserie");
      const targetState = this.getAttribute("data-target-state");

      // Show state change modal/form
      showStateChangeForm(numSerie, targetState);
    });
  });
}

/**
 * Set up event listeners for history buttons
 */
function setupHistoryButtonListeners() {
  document.querySelectorAll(".btn-history").forEach(button => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      const numSerie = this.getAttribute("data-numserie");
      showMaterialHistory(numSerie);
    });
  });
}

/**
 * Set up event listener for the state change form submission
 */
// Mapping from state string to stock number
const stateToStock = {
  "en-service": 0,
  "en-stock": 1,
  endommage: 2,
  casse: 3,
};

function setupStateChangeFormSubmission() {
  const form = document.getElementById("state-change-form");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      const formData = new FormData(this);
      const numSerie = document.getElementById("state-change-numserie").value;
      const targetState = document.getElementById("state-change-target").value;
      const stockValue =
        stateToStock[targetState] !== undefined ? stateToStock[targetState] : 0;
      formData.set("target_state", stockValue); // send as number

      // Make sure action parameter is set
      if (!formData.has("action")) {
        formData.append("action", "change_state");
      }

      // Show loading message
      showNotification("Changement d'état en cours...", "info");

      // Send the request via fetch
      fetch(this.action, {
        method: "POST",
        body: formData,
      })
        .then(response => {
          if (!response.ok) {
            throw new Error(`Server responded with status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            // Close the modal
            closeStateChangeModal();

            // Map back to state string for UI update
            const stockToState = {
              0: "en-service",
              1: "en-stock",
              2: "endommage",
              3: "casse",
            };
            const newState = stockToState[data.newState] || "en-service";

            // Update the UI to reflect the state change
            updateMaterialStateInUI(numSerie, newState);
          } else {
            showNotification(
              "Erreur: " + (data.message || "Une erreur inconnue est survenue"),
              "error"
            );
          }
        })
        .catch(error => {
          console.error("Error changing material state:", error);
          showNotification(
            "Une erreur est survenue lors du changement d'état: " +
              error.message,
            "error"
          );
        });
    });
  }
}

/**
 * Update the material state in the UI after a successful state change
 * @param {string} numSerie - The material's serial number
 * @param {string} newState - The new state of the material
 */
function updateMaterialStateInUI(numSerie, newState) {
  // Normalize state value
  let normalizedState = newState;
  switch (newState) {
    case "en-service":
    case "en-stock":
    case "endommage":
    case "casse":
      // already valid
      break;
    default:
      // fallback to en-stock if unknown
      normalizedState = "en-stock";
  }

  // Find the row with this material
  const row = document.querySelector(`tr[data-numserie="${numSerie}"]`);
  if (row) {
    // Update the state cell
    const stateCell = row.querySelector(".materiel-state-value");
    if (stateCell) {
      // Remove all state classes
      stateCell.classList.remove(
        "state-en-service",
        "state-en-stock",
        "state-endommage",
        "state-casse"
      );

      // Add the new state class
      stateCell.classList.add(`state-${normalizedState}`);

      // Update the data-state attribute
      stateCell.setAttribute("data-state", normalizedState);

      // Update the text content based on the state
      let stateText = "";
      switch (normalizedState) {
        case "en-service":
          stateText = "En service";
          break;
        case "en-stock":
          stateText = "En stock";
          break;
        case "endommage":
          stateText = "Endommagé";
          break;
        case "casse":
          stateText = "Cassé";
          break;
        default:
          stateText = normalizedState;
      }
      stateCell.textContent = stateText;

      // Update the parent <tr>'s data-state attribute for filtering
      row.setAttribute("data-state", normalizedState);

      // Debug output
      console.log(`Updated material ${numSerie} to state:`, normalizedState);
      console.log(
        `.materiel-state-value data-state:`,
        stateCell.getAttribute("data-state")
      );
      console.log(`<tr> data-state:`, row.getAttribute("data-state"));

      // Show a success notification
      showNotification(`État du matériel mis à jour: ${stateText}`, "success");

      // Re-filter the table so the row appears in the correct tab
      filterMaterielByState();
    }
  }
}

/**
 * Show the state change form with the appropriate fields based on target state
 * @param {string} numSerie - The material's serial number
 * @param {string} targetState - The target state for the material
 */
function showStateChangeForm(numSerie, targetState) {
  const modal = document.getElementById("state-change-modal");
  const form = document.getElementById("state-change-form");
  const stateLabel = document.getElementById("state-change-label");
  const causeField = document.getElementById("damage-cause-field");

  // Set the form values
  document.getElementById("state-change-numserie").value = numSerie;
  document.getElementById("state-change-target").value = targetState;

  // Show/hide fields based on target state
  if (targetState === "endommage") {
    stateLabel.textContent = "Marquer comme endommagé";
    causeField.style.display = "block";
  } else if (targetState === "casse") {
    stateLabel.textContent = "Marquer comme cassé";
    causeField.style.display = "block";
  } else if (targetState === "en-service") {
    stateLabel.textContent = "Mettre en service";
    causeField.style.display = "none";
  } else if (targetState === "en-stock") {
    stateLabel.textContent = "Mettre en stock";
    causeField.style.display = "none";
  }

  // Clear only the text areas, not the hidden fields
  const textareas = form.querySelectorAll("textarea");
  textareas.forEach(textarea => {
    textarea.value = "";
  });

  // Always set the form action for AJAX state change
  form.setAttribute("action", "php/change_material_state.php");
  console.log("State change form action set to:", form.getAttribute("action"));

  // Show the modal
  modal.style.display = "block";
}

/**
 * Close the state change modal
 */
function closeStateChangeModal() {
  const modal = document.getElementById("state-change-modal");
  modal.style.display = "none";
}

/**
 * Show the material history modal for a specific material
 * @param {string} numSerie - The material's serial number
 */
function showMaterialHistory(numSerie) {
  // Show loading indicator
  const historyContent = document.getElementById("history-content");
  historyContent.innerHTML =
    "<div class='loading-spinner'><div></div><div></div><div></div><div></div></div>";

  // Show the modal first for better UX
  document.getElementById("history-modal").style.display = "block";

  // Fetch the material history via AJAX
  fetch(`php/get_material_history.php?numserie=${encodeURIComponent(numSerie)}`)
    .then(response => response.json())
    .then(data => {
      historyContent.innerHTML = "";

      if (
        !data.success ||
        !Array.isArray(data.history) ||
        data.history.length === 0
      ) {
        historyContent.innerHTML =
          "<p class='no-history'>Aucun historique disponible pour ce matériel.</p>";
      } else {
        // Create a scrollable container for the table
        const scrollContainer = document.createElement("div");
        scrollContainer.style.overflowX = "auto";
        scrollContainer.style.maxWidth = "100%";

        // Create history table
        const table = document.createElement("table");
        table.className = "history-table";
        table.style.minWidth = "700px";

        // Create table header
        const thead = document.createElement("thead");
        const headerRow = document.createElement("tr");
        const headers = [
          "Date",
          "État précédent",
          "Nouvel état",
          "Utilisateur",
          "Cause",
          "Notes",
        ];

        headers.forEach(header => {
          const th = document.createElement("th");
          th.textContent = header;
          headerRow.appendChild(th);
        });

        thead.appendChild(headerRow);
        table.appendChild(thead);

        // Create table body
        const tbody = document.createElement("tbody");

        data.history.forEach(entry => {
          const row = document.createElement("tr");

          // Date column
          const dateCell = document.createElement("td");
          dateCell.textContent = entry.date || entry.date_change || "-";
          row.appendChild(dateCell);

          // Previous state column
          const prevStateCell = document.createElement("td");
          if (entry.prev_state) {
            const prevStateSpan = document.createElement("span");
            prevStateSpan.className = `history-state history-state-${String(
              entry.prev_state
            )
              .replace(" ", "-")
              .toLowerCase()}`;
            prevStateSpan.textContent = entry.prev_state;
            prevStateCell.appendChild(prevStateSpan);
          } else {
            prevStateCell.textContent = "-";
          }
          row.appendChild(prevStateCell);

          // New state column
          const newStateCell = document.createElement("td");
          if (entry.new_state) {
            const newStateSpan = document.createElement("span");
            newStateSpan.className = `history-state history-state-${String(
              entry.new_state
            )
              .replace(" ", "-")
              .toLowerCase()}`;
            newStateSpan.textContent = entry.new_state;
            newStateCell.appendChild(newStateSpan);
          } else {
            newStateCell.textContent = "-";
          }
          row.appendChild(newStateCell);

          // User column
          const userCell = document.createElement("td");
          userCell.textContent = entry.user_name || entry.user_id || "-";
          row.appendChild(userCell);

          // Cause column
          const causeCell = document.createElement("td");
          causeCell.textContent =
            entry.cause ||
            entry.notes_cause ||
            entry.cause_du_dommage ||
            entry.damage_cause ||
            "-";
          row.appendChild(causeCell);

          // Notes column
          const notesCell = document.createElement("td");
          notesCell.textContent = entry.notes || "-";
          row.appendChild(notesCell);

          tbody.appendChild(row);
        });

        table.appendChild(tbody);
        scrollContainer.appendChild(table);
        historyContent.appendChild(scrollContainer);
      }

      // Add a Fermer button
      const closeBtn = document.createElement("button");
      closeBtn.textContent = "Fermer";
      closeBtn.className = "btn-cancel";
      closeBtn.style.marginTop = "16px";
      closeBtn.onclick = closeHistoryModal;
      historyContent.appendChild(closeBtn);
    })
    .catch(error => {
      console.error("Error fetching material history:", error);
      historyContent.innerHTML =
        "<p class='error-message'>Une erreur est survenue lors de la récupération de l'historique.</p>";
    });
}

/**
 * Close the material history modal
 */
function closeHistoryModal() {
  document.getElementById("history-modal").style.display = "none";
}

/**
 * Display a notification message
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, error, etc)
 */
function showNotification(message, type = "info") {
  // Create notification element if it doesn't exist
  let notification = document.querySelector(".notification");
  if (!notification) {
    notification = document.createElement("div");
    notification.className = "notification";
    document.body.appendChild(notification);
  }

  // Set the message and type
  notification.textContent = message;
  notification.className = "notification notification-" + type;

  // Show the notification
  notification.style.display = "block";

  // Auto-hide after a delay
  setTimeout(() => {
    notification.style.opacity = "0";
    setTimeout(() => {
      notification.style.display = "none";
      notification.style.opacity = "1";
    }, 500);
  }, 3000);
}
