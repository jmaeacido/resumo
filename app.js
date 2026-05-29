let currentMode = "resume";
let latestReport = null;

const form = document.getElementById("resumeForm");
const resumeText = document.getElementById("resumeText");
const resumeFile = document.getElementById("resumeFile");
const jobFields = document.getElementById("jobFields");
const jobTitle = document.getElementById("jobTitle");
const jobDescription = document.getElementById("jobDescription");
const tabs = document.querySelectorAll(".mode-tab");
const printButton = document.getElementById("printButton");
const submitButton = form.querySelector("button[type='submit']");

tabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    currentMode = tab.dataset.mode;
    tabs.forEach((button) => {
      const active = button === tab;
      button.classList.toggle("active", active);
      button.setAttribute("aria-selected", String(active));
    });
    jobFields.hidden = currentMode !== "job";
    syncJobFields();
  });
});

resumeFile.addEventListener("change", () => {
  const file = resumeFile.files[0];
  if (!file) return;
  if (file.name.toLowerCase().endsWith(".txt")) {
    file.text().then((text) => {
      if (!resumeText.value.trim()) resumeText.value = text;
    });
  }
});

printButton.addEventListener("click", () => {
  if (latestReport?.pdf_url) {
    window.open(latestReport.pdf_url, "_blank");
    return;
  }
  window.print();
});

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  await analyze();
});

async function analyze() {
  const formData = new FormData();
  formData.append("mode", currentMode);
  formData.append("resumeText", resumeText.value);
  if (currentMode === "job") {
    formData.append("jobTitle", jobTitle.value);
    formData.append("jobDescription", jobDescription.value);
  }
  if (resumeFile.files[0]) {
    formData.append("resumeFile", resumeFile.files[0]);
  }

  setBusy(true);
  try {
    const response = await fetch("api/analyze.php", {
      method: "POST",
      body: formData
    });
    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.error || "Analysis failed.");
    }
    latestReport = data.analysis;
    renderReport(latestReport);
  } catch (error) {
    renderError(error.message);
  } finally {
    setBusy(false);
  }
}

function syncJobFields() {
  const enabled = currentMode === "job";
  jobTitle.disabled = !enabled;
  jobDescription.disabled = !enabled;
}

function setBusy(isBusy) {
  submitButton.disabled = isBusy;
  submitButton.textContent = isBusy ? "Analyzing..." : "Analyze Resume";
}

function renderReport(analysis) {
  document.getElementById("reportMode").textContent = analysis.mode === "job" ? "Resume + job description matching" : "Resume-only analysis";
  document.getElementById("reportTitle").textContent = analysis.title;
  document.getElementById("reportSubtitle").textContent = `${analysis.subtitle} Engine: ${analysis.engine}.`;
  document.getElementById("overallScore").textContent = analysis.overall;
  printButton.textContent = "Download PDF";

  const metricGrid = document.getElementById("metricGrid");
  metricGrid.innerHTML = analysis.metrics.map(([label, value, note]) => `
    <article class="metric-card">
      <span>${escapeHtml(label)}</span>
      <strong>${escapeHtml(value)}</strong>
      <small>${escapeHtml(note)}</small>
    </article>
  `).join("");

  document.getElementById("breakdownList").innerHTML = Object.entries(analysis.scores).map(([name, score]) => `
    <div class="score-row">
      <span>${escapeHtml(name)}</span>
      <div class="meter"><div style="width:${Number(score)}%"></div></div>
      <strong>${Number(score)}</strong>
    </div>
  `).join("");

  renderList("strengthsList", analysis.strengths);
  renderList("weaknessesList", analysis.weaknesses);
  renderList("recommendationsList", analysis.recommendations);
  renderList("keywordsList", analysis.keywords?.length ? analysis.keywords : ["Available in Job Match mode when a job description is provided."], Boolean(analysis.keywords?.length));
}

function renderList(id, items, asTags = false) {
  const list = document.getElementById(id);
  list.className = asTags ? "tag-list" : "";
  list.innerHTML = items.map((item) => `<li>${escapeHtml(item)}</li>`).join("");
}

function renderError(message) {
  document.getElementById("reportTitle").textContent = "Analysis needs attention";
  document.getElementById("reportSubtitle").textContent = message;
  document.getElementById("overallScore").textContent = "--";
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

syncJobFields();
