function showTab(tabName) {
  document
    .querySelectorAll(".tab-content")
    .forEach((c) => c.classList.remove("active"));
  document
    .querySelectorAll(".tab-btn")
    .forEach((b) => b.classList.remove("active"));
  const content = document.getElementById(tabName);
  if (content) content.classList.add("active");
  const btn = document.querySelector(`[onclick="showTab('${tabName}')"]`);
  if (btn) btn.classList.add("active");

  const url = new URL(window.location);
  url.searchParams.set("tab", tabName);
  window.history.replaceState({}, "", url.toString());
}

function exportTableToExcel(tableId, filename = "") {
  const table = document.getElementById(tableId);
  if (!table) {
    console.error("Table not found!");
    return;
  }

  // Clone the table to avoid modifying the original table
  const clonedTable = table.cloneNode(true);

  // Remove the "Actions" header and column from the cloned table
  const actionHeaderIndex = Array.from(
    clonedTable.querySelectorAll("th")
  ).findIndex((th) => th.textContent.trim() === "Actions");
  if (actionHeaderIndex !== -1) {
    clonedTable.querySelector("thead tr").deleteCell(actionHeaderIndex);
    Array.from(clonedTable.querySelectorAll("tbody tr")).forEach((row) => {
      row.deleteCell(actionHeaderIndex);
    });
  }

  const wb = XLSX.utils.table_to_book(clonedTable, { sheet: "Sheet1" });
  const wbout = XLSX.write(wb, { bookType: "xlsx", type: "binary" });

  function s2ab(s) {
    const buf = new ArrayBuffer(s.length);
    const view = new Uint8Array(buf);
    for (let i = 0; i < s.length; i++) {
      view[i] = s.charCodeAt(i) & 0xff;
    }
    return buf;
  }

  const blob = new Blob([s2ab(wbout)], { type: "application/octet-stream" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.style.display = "none";
  link.download = filename || "export.xlsx";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  // Check if we have showForm parameter and redirect immediately to clean URL
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("showForm")) {
    const currentTab = urlParams.get("tab") || "materiel";
    const currentSte = urlParams.get("ste") || "prod";
    let cleanUrl = `index.php?tab=${currentTab}&ste=${currentSte}`;

    // Preserve state and inventaire_mode if they exist
    if (urlParams.get("state")) {
      cleanUrl += `&state=${urlParams.get("state")}`;
    }
    if (urlParams.get("inventaire_mode")) {
      cleanUrl += `&inventaire_mode=${urlParams.get("inventaire_mode")}`;
    }

    // Immediately redirect to clean URL
    window.location.href = cleanUrl;
  }
}

// Function to immediately enable all tabs and hide forms (for instant feedback)
function enableAllTabsAndHideForms() {
  // Enable all tab buttons
  document.querySelectorAll(".tab-btn").forEach((button) => {
    button.removeAttribute("disabled");
    button.style.opacity = "1";
    button.style.pointerEvents = "auto";
    button.style.color = "";
    button.style.cursor = "pointer";
  });

  // Hide all forms
  document.querySelectorAll(".section[class*='-form']").forEach((form) => {
    form.classList.add("hide");
  });
}

// Function to redirect to clean URL without showForm parameter
function redirectToCleanURL() {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get("showForm")) {
    // Wait a bit for any ongoing action to complete, then redirect to clean URL
    setTimeout(() => {
      const currentTab = urlParams.get("tab") || "materiel";
      const currentSte = urlParams.get("ste") || "prod";
      let cleanUrl = `index.php?tab=${currentTab}&ste=${currentSte}`;

      // Preserve state and inventaire_mode if they exist
      if (urlParams.get("state")) {
        cleanUrl += `&state=${urlParams.get("state")}`;
      }
      if (urlParams.get("inventaire_mode")) {
        cleanUrl += `&inventaire_mode=${urlParams.get("inventaire_mode")}`;
      }

      window.location.href = cleanUrl;
    }, 100);
  }
}

function showForm(formClass) {
  const form = document.querySelector("." + formClass);
  if (form) {
    form.classList.remove("hide");
    form.scrollIntoView({ behavior: "smooth", block: "start" });
  }
}

function hideForm(formClass) {
  const el = document.querySelector("." + formClass);
  if (el) {
    el.classList.add("hide");
  }
}

// Hide form and remove 'showForm' from URL
function hideFormAndResetURL(formSelector, tabName, ste) {
  const form = document.querySelector(formSelector);
  if (form) {
    form.classList.add("hide");
  }
  // Construct the new URL without the 'showForm' parameter
  const newUrl = `index.php?tab=${tabName}&ste=${ste}`;
  window.location.href = newUrl;
}

function handleFormSubmit(form) {
  // We let the server handle form visibility on success (redirect) or error (re-render)
  /* if (form.checkValidity()) {
    const formContainer = form.closest("[class$='-form']");
    if (formContainer) {
      const formClass = formContainer.classList[1];
      setTimeout(() => hideForm(formClass), 150);
    }
  } */
  return true;
}

function performSearch(searchType) {
  const searchInput = document.getElementById(`search-${searchType}`);
  const table = document.getElementById(`${searchType}-table`);
  const searchTerm = searchInput.value.toLowerCase().trim();

  if (!table) return;

  const rows = table
    .getElementsByTagName("tbody")[0]
    .getElementsByTagName("tr");

  // Special handling for materiel table to integrate tab filtering
  if (searchType === "materiel") {
    const activeTab = document.querySelector(".materiel-state-tab.active");
    const activeState = activeTab
      ? activeTab.getAttribute("data-state")
      : "all";

    const checkedFilters = Array.from(
      document.querySelectorAll("#materiel .search-filter:checked")
    ).map((checkbox) => checkbox.getAttribute("data-column"));

    Array.from(rows).forEach((row) => {
      const stateCell = row.querySelector(".materiel-state-value");
      const materialState = stateCell
        ? stateCell.getAttribute("data-state")
        : null;

      // 1. Determine if the row matches the active tab's state
      const matchesTab = activeState === "all" || materialState === activeState;

      // 2. Determine if the row matches the search term and filters
      let matchesSearch = false;
      // If there's no search term, it's a match.
      if (searchTerm === "") {
        matchesSearch = true;
      } else if (checkedFilters.length > 0) {
        const cells = row.getElementsByTagName("td");
        const columnMapping = getColumnMapping("materiel");

        for (const column of checkedFilters) {
          const cellIndex = columnMapping[column];
          if (cellIndex !== undefined && cells[cellIndex]) {
            const cellText = cells[cellIndex].textContent.toLowerCase();
            if (cellText.includes(searchTerm)) {
              matchesSearch = true;
              break; // Found a match, no need to check other columns
            }
          }
        }
      } else {
        // No search term and no filters checked, so it's a match.
        matchesSearch = true;
      }

      // A row is visible only if it matches both the tab filter AND the search filter
      row.style.display = matchesTab && matchesSearch ? "" : "none";
    });

    // After filtering, we don't need the generic logic below for materiel.
    return;
  }

  // --- Generic search logic for all other tables ---

  // Container mapping
  const containerMapping = {
    materiel: "materiel",
    utilisateur: "utilisateurs",
    marque: "marques",
    type: "types",
    service: "services",
    fournisseur: "fournisseurs",
  };

  const containerId = containerMapping[searchType] || searchType;
  const checkedFilters = Array.from(
    document.querySelectorAll(`#${containerId} .search-filter:checked`)
  ).map((checkbox) => checkbox.getAttribute("data-column"));

  // If no filters are checked, show all rows
  if (checkedFilters.length === 0) {
    Array.from(rows).forEach((row) => {
      row.style.display = "";
    });
    return;
  }

  Array.from(rows).forEach((row) => {
    let shouldShow = false;

    if (searchTerm === "") {
      shouldShow = true;
    } else {
      const cells = row.getElementsByTagName("td");
      const columnMapping = getColumnMapping(searchType);

      checkedFilters.forEach((column) => {
        const cellIndex = columnMapping[column];
        if (cellIndex !== undefined && cells[cellIndex]) {
          const cellText = cells[cellIndex].textContent.toLowerCase();
          if (cellText.includes(searchTerm)) {
            shouldShow = true;
          }
        }
      });
    }

    row.style.display = shouldShow ? "" : "none";
  });
}

function getColumnMapping(searchType) {
  const mappings = {
    materiel: {
      NumSerie: 0,
      NomPrenom: 1,
      Marque: 2,
      TypeLibelle: 3,
      Model: 4,
      Dateentree: 5,
      État: 6,
      observation: 7,
    },
    utilisateur: {
      Compte: 0,
      NomPrenom: 1,
      Email: 2,
      Tel: 3,
      ServiceLibelle: 4,
    },
    marque: {
      Code: 0,
      Marque: 1,
    },
    type: {
      CodeType: 0,
      Libelle: 1,
    },
    service: {
      CodeService: 0,
      Libelle: 1,
    },
    fournisseur: {
      Email: 0,
      CompanyName: 1,
      NomComplet: 2,
      Adress: 3,
      TelFix: 4,
      TelMobile: 5,
    },
  };

  return mappings[searchType] || {};
}

function clearSearch(searchType) {
  const searchInput = document.getElementById(`search-${searchType}`);

  const containerMapping = {
    materiel: "materiel",
    utilisateur: "utilisateurs",
    marque: "marques",
    type: "types",
    service: "services",
    fournisseur: "fournisseurs",
  };

  const containerId = containerMapping[searchType] || searchType;
  const checkboxes = document.querySelectorAll(
    `#${containerId} .search-filter`
  );

  if (searchInput) {
    searchInput.value = "";
  }

  checkboxes.forEach((checkbox) => {
    const isDefault =
      checkbox.hasAttribute("checked") ||
      (searchType === "materiel" &&
        ["NumSerie", "NomPrenom"].includes(
          checkbox.getAttribute("data-column")
        )) ||
      (searchType === "utilisateur" &&
        checkbox.getAttribute("data-column") === "NomPrenom") ||
      (searchType === "marque" &&
        checkbox.getAttribute("data-column") === "Marque") ||
      (searchType === "type" &&
        checkbox.getAttribute("data-column") === "Libelle") ||
      (searchType === "service" &&
        checkbox.getAttribute("data-column") === "Libelle") ||
      (searchType === "fournisseur" &&
        ["CompanyName", "NomComplet"].includes(
          checkbox.getAttribute("data-column")
        ));

    checkbox.checked = isDefault;
  });

  performSearch(searchType);
}

function initializeSearch() {
  const searchTypes = [
    "materiel",
    "utilisateur",
    "marque",
    "type",
    "service",
    "fournisseur",
  ];

  const containerMapping = {
    materiel: "materiel",
    utilisateur: "utilisateurs",
    marque: "marques",
    type: "types",
    service: "services",
    fournisseur: "fournisseurs",
  };

  searchTypes.forEach((searchType) => {
    const searchInput = document.getElementById(`search-${searchType}`);
    const containerId = containerMapping[searchType] || searchType;
    const searchContainer = document.querySelector(
      `#${containerId} .search-container`
    );

    if (searchInput && searchContainer) {
      searchInput.addEventListener("input", () => {
        performSearch(searchType);
      });

      const checkboxes = searchContainer.querySelectorAll(".search-filter");
      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", () => {
          performSearch(searchType);
        });
      });

      searchInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          performSearch(searchType);
        }
      });
    }
  });
}

// Function to add event listeners to cancel buttons and other navigation elements
function initializeFormStateClearers() {
  // Add event listeners to all cancel/annuler buttons to clear form state
  document.querySelectorAll("a.btn-cancel, a.btn-close").forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      // Immediately enable tabs and hide forms
      enableAllTabsAndHideForms();
      // Then redirect to clean URL
      redirectToCleanURL();
    });
  });

  // Add event listeners to ALL export buttons to immediately clear form state
  document.querySelectorAll(".btn-export").forEach((button) => {
    button.addEventListener("click", function (e) {
      // Immediately enable tabs and hide forms for instant feedback
      enableAllTabsAndHideForms();
    });
  });

  // Add event listeners to tab buttons to clear form state when switching tabs
  document.querySelectorAll(".tab-btn").forEach((button) => {
    button.addEventListener("click", function (e) {
      // If we're in a form view, clear it before navigating
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get("showForm")) {
        e.preventDefault();
        // Immediately enable all tabs
        enableAllTabsAndHideForms();
        // Extract the tab from the onclick or href
        const href =
          this.getAttribute("onclick") || this.getAttribute("href") || "";
        const match = href.match(/tab=([^&']+)/);
        if (match) {
          const targetTab = match[1];
          const currentSte = urlParams.get("ste") || "prod";
          window.location.href = `index.php?tab=${targetTab}&ste=${currentSte}`;
        }
      }
    });
  });

  // Add event listener to state filter links to clear form state
  document.querySelectorAll(".state-filters a").forEach((link) => {
    link.addEventListener("click", function (e) {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get("showForm")) {
        e.preventDefault();
        // Get the href and remove any showForm parameter
        let href = this.getAttribute("href");
        if (href) {
          // Remove showForm parameter if present
          href = href.replace(/[&?]showForm=[^&]*/g, "");
          window.location.href = href;
        }
      }
    });
  });
}

document.addEventListener("DOMContentLoaded", function () {
  const activeContent = document.querySelector(".tab-content.active");
  if (activeContent) {
    showTab(activeContent.id);
  }

  const entities = [
    "materiel",
    "utilisateur",
    "marque",
    "type",
    "service",
    "fournisseur",
  ];

  entities.forEach((entity) => {
    const addBtn = document.getElementById(`add-${entity}-btn`);
    const closeBtn = document.getElementById(`close-${entity}-form-btn`);
    const formClass = `${entity}-form`;

    if (addBtn) {
      addBtn.addEventListener("click", () => showForm(formClass));
    }

    if (closeBtn) {
      closeBtn.addEventListener("click", () => hideForm(formClass));
    }
  });

  document.querySelectorAll("form").forEach((form) => {
    form.addEventListener("submit", function (e) {
      // Skip validation for Fin Inventaire form
      if (form.id === "fin-inventaire-form") return;
      let valid = true;
      form.querySelectorAll("[required]").forEach((f) => {
        if (!f.value.trim()) {
          f.style.borderColor = "#e74c3c";
          valid = false;
        } else {
          f.style.borderColor = "#27ae60";
        }
      });
      if (!valid) {
        e.preventDefault();
        alert("Veuillez remplir tous les champs obligatoires.");
      }
    });
  });

  const alerts = document.querySelectorAll(".alert-success, .alert-error");
  alerts.forEach((alert) => {
    setTimeout(() => {
      alert.style.display = "none";
    }, 5000);
  });

  const themeToggle = document.getElementById("theme-toggle");
  if (themeToggle) {
    themeToggle.addEventListener("change", function () {
      const newTheme = this.checked ? "comm" : "prod";
      const url = new URL(window.location);
      url.searchParams.set("ste", newTheme);
      url.searchParams.set("tab", "materiel");
      window.location.href = url.toString();
    });
  }

  initializeSearch();
  initializeFormStateClearers();

  // Fix for Annuler and Annuler Inventaire buttons
  document.querySelectorAll(".btn-cancel").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      // If the button is for cancelling inventory, always go to the clean URL
      if (this.textContent.includes("Inventaire")) {
        e.preventDefault();
        const url = new URL(window.location.href);
        url.searchParams.set("tab", "materiel");
        url.searchParams.set("ste", url.searchParams.get("ste") || "prod");
        url.searchParams.delete("inventaire_mode");
        url.searchParams.delete("state");
        window.location.href = url.pathname + "?" + url.searchParams.toString();
      }
    });
  });
});

function toggleDamageCause(stockValue) {
  const damageCauseGroup = document.getElementById("damage-cause-group");
  if (stockValue == "2" || stockValue == "3") {
    // Endommagé or Cassé
    damageCauseGroup.style.display = "block";
  } else {
    damageCauseGroup.style.display = "none";
  }
}
