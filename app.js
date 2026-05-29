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
const fileLabel = document.querySelector(".custom-file-label");

const processSteps = [
  { id: "prepare", label: "Preparing resume inputs", target: 10 },
  { id: "upload", label: "Uploading resume", target: 28 },
  { id: "extract", label: "Extracting document content", target: 44 },
  { id: "score", label: "Scoring resume quality", target: 64 },
  { id: "ai", label: "Enhancing insights", target: 84 },
  { id: "report", label: "Building report", target: 96 }
];

let progressTimer = null;
let activeStepIndex = 0;
let progressPercent = 0;

tabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    currentMode = tab.dataset.mode;
    tabs.forEach((button) => {
      const active = button === tab;
      button.classList.toggle("active", active);
      button.classList.toggle("btn-primary", active);
      button.classList.toggle("btn-outline-primary", !active);
      button.setAttribute("aria-selected", String(active));
    });
    jobFields.hidden = currentMode !== "job";
    syncJobFields();
  });
});

resumeFile.addEventListener("change", () => {
  const file = resumeFile.files[0];
  fileLabel.textContent = file ? file.name : "Choose TXT, PDF, DOC, or DOCX";
  if (!file) return;
  if (file.name.toLowerCase().endsWith(".txt")) {
    file.text().then((text) => {
      if (!resumeText.value.trim()) {
        resumeText.value = text;
        showToast("TXT resume loaded into the editor.");
      }
    });
  }
});

printButton.addEventListener("click", () => {
  if (latestReport?.pdf_url) {
    window.open(latestReport.pdf_url, "_blank");
    return;
  }
  showAlert("No report yet", "Run an analysis first, then Resumo can generate the PDF report.", "info");
});

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  await analyze();
});

async function analyze() {
  if (!resumeText.value.trim() && !resumeFile.files[0]) {
    showAlert("Resume required", "Paste resume text or upload a supported TXT, PDF, DOC, or DOCX file.", "warning");
    resumeText.focus();
    return;
  }

  if (currentMode === "job" && !jobDescription.value.trim()) {
    showAlert("Job description required", "Paste the target job description before running Job Match.", "warning");
    jobDescription.focus();
    return;
  }

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
  startProgress(Boolean(resumeFile.files[0]));
  try {
    const data = await postAnalysis(formData);
    setProgressStep("report");
    setProgress(100, "Report ready");
    latestReport = data.analysis;
    renderReport(latestReport);
    await finishProgressModal();
    showToast("Analysis complete. Your report is ready.");
  } catch (error) {
    setProgress(progressPercent, "Analysis needs attention");
    renderError(error.message);
    closeProgressModal();
    showAlert("Analysis failed", error.message, "error");
  } finally {
    setBusy(false);
    stopProgress();
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
  const aiStatus = analysis.ai_status ? ` ${analysis.ai_status}` : "";
  document.getElementById("reportSubtitle").textContent = `${analysis.subtitle} Engine: ${analysis.engine}.${aiStatus}`;
  document.getElementById("overallScore").textContent = analysis.overall;
  printButton.textContent = "Download PDF";

  const metricGrid = document.getElementById("metricGrid");
  const icons = ["fa-robot", "fa-briefcase", "fa-layer-group"];
  const colors = ["bg-info", "bg-success", "bg-warning"];
  metricGrid.innerHTML = analysis.metrics.map(([label, value, note], index) => `
    <div class="col-md-4">
      <div class="info-box">
        <span class="info-box-icon ${colors[index % colors.length]}"><i class="fas ${icons[index % icons.length]}"></i></span>
        <div class="info-box-content">
          <span class="info-box-text">${escapeHtml(label)}</span>
          <span class="info-box-number">${escapeHtml(value)}</span>
          <span class="info-box-note">${escapeHtml(note)}</span>
        </div>
      </div>
    </div>
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
  const keywordsPanel = document.getElementById("keywordsPanel");
  keywordsPanel.hidden = analysis.mode !== "job";
  if (analysis.mode === "job") {
    renderList("keywordsList", analysis.keywords?.length ? analysis.keywords : ["No missing keywords detected."], Boolean(analysis.keywords?.length));
  } else {
    renderList("keywordsList", []);
  }
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

function postAnalysis(formData) {
  return new Promise((resolve, reject) => {
    const request = new XMLHttpRequest();
    request.open("POST", "api/analyze.php");
    request.responseType = "json";

    request.upload.addEventListener("progress", (event) => {
      if (!event.lengthComputable) return;
      const uploaded = event.loaded / event.total;
      setProgressStep("upload");
      setProgress(10 + Math.round(uploaded * 18), "Uploading resume");
    });

    request.addEventListener("load", () => {
      const data = request.response || {};
      if (request.status >= 200 && request.status < 300) {
        resolve(data);
        return;
      }
      reject(new Error(data.error || "Analysis failed."));
    });

    request.addEventListener("error", () => {
      reject(new Error("Unable to reach the analysis service."));
    });

    request.send(formData);
  });
}

function startProgress(hasFile) {
  stopProgress();
  activeStepIndex = 0;
  progressPercent = 0;
  processSteps.find((step) => step.id === "upload").label = hasFile ? "Uploading resume" : "No file upload";
  openProgressModal();
  setProgress(4, "Preparing resume inputs");

  const schedule = hasFile ? processSteps : processSteps.filter((step) => step.id !== "upload");
  let scheduleIndex = 0;

  progressTimer = window.setInterval(() => {
    const step = schedule[Math.min(scheduleIndex, schedule.length - 1)];
    setProgressStep(step.id);

    const ceiling = Math.min(step.target, 94);
    if (progressPercent < ceiling) {
      setProgress(progressPercent + Math.max(1, Math.round((ceiling - progressPercent) * 0.22)), step.label);
      return;
    }

    if (scheduleIndex < schedule.length - 1) {
      scheduleIndex += 1;
    }
  }, 650);
}

function stopProgress() {
  if (progressTimer) {
    window.clearInterval(progressTimer);
    progressTimer = null;
  }
}

function finishProgressModal() {
  return new Promise((resolve) => {
    window.setTimeout(() => {
      closeProgressModal();
      resolve();
    }, 450);
  });
}

function closeProgressModal() {
  if (window.Swal && Swal.isVisible()) {
    Swal.close();
  }
}

function setProgress(percent, label) {
  progressPercent = Math.max(0, Math.min(100, percent));
  updateProgressModal(label);
}

function setProgressStep(stepId) {
  const nextIndex = processSteps.findIndex((step) => step.id === stepId);
  if (nextIndex === -1) return;
  activeStepIndex = nextIndex;
  updateProgressModal(processSteps[activeStepIndex].label);
}

function openProgressModal() {
  if (!window.Swal) return;
  Swal.fire({
    title: "Analyzing resume",
    html: progressMarkup("Preparing resume inputs"),
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      updateProgressModal("Preparing resume inputs");
    }
  });
}

function updateProgressModal(label) {
  const container = document.querySelector(".swal-progress");
  if (!container) return;

  const roundedProgress = Math.round(progressPercent);
  const labelNode = container.querySelector("[data-progress-label]");
  const valueNode = container.querySelector("[data-progress-value]");
  const barNode = container.querySelector("[data-progress-bar]");
  const listNode = container.querySelector("[data-process-list]");

  labelNode.textContent = label;
  valueNode.textContent = `${roundedProgress}%`;
  barNode.style.width = `${roundedProgress}%`;
  barNode.setAttribute("aria-valuenow", String(roundedProgress));
  listNode.innerHTML = processSteps.map((step, index) => {
    const state = progressPercent === 100 || index < activeStepIndex ? "done" : (index === activeStepIndex ? "active" : "");
    return `<li class="${state}"><span></span>${escapeHtml(step.label)}</li>`;
  }).join("");
}

function progressMarkup(label) {
  return `
    <div class="swal-progress">
      <div class="swal-progress-value">
        <span data-progress-label>${escapeHtml(label)}</span>
        <strong data-progress-value>0%</strong>
      </div>
      <div class="swal-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div class="swal-progress-bar" data-progress-bar></div>
      </div>
      <ol class="swal-process-list" data-process-list></ol>
    </div>
  `;
}

function showToast(message) {
  if (!window.Swal) return;
  Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 2600,
    timerProgressBar: true
  }).fire({
    icon: "success",
    title: message
  });
}

function showAlert(title, message, icon = "info") {
  if (!window.Swal) return;
  Swal.fire({
    title,
    text: message,
    icon,
    confirmButtonColor: "#0c6b63",
    background: "#ffffff",
    color: "#18212a"
  });
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
setProgress(0, "Ready");
