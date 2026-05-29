let currentMode = "resume";
let latestReport = null;
let previewMode = "formatted";

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
const previewButtons = document.querySelectorAll(".preview-mode");
const resumePreview = document.getElementById("resumePreview");
const previewSource = document.getElementById("previewSource");
const previewWords = document.getElementById("previewWords");
const previewLines = document.getElementById("previewLines");
const previewSectionsCount = document.getElementById("previewSectionsCount");
const previewSectionChips = document.getElementById("previewSectionChips");

const sectionPatterns = {
  contact: /\b(email|phone|linkedin|portfolio|github|contact)\b/i,
  summary: /\b(summary|profile|objective|professional summary|career summary)\b/i,
  experience: /\b(experience|employment|work history|professional experience)\b/i,
  education: /\b(education|degree|university|college|bachelor|master|phd|diploma)\b/i,
  skills: /\b(skills|technical skills|core competencies|technologies)\b/i,
  certifications: /\b(certifications?|licenses?|accreditations?)\b/i,
  projects: /\b(projects?|portfolio|selected work)\b/i,
  awards: /\b(awards?|honors?|achievements?)\b/i
};

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
  if (!file) {
    updateResumePreview();
    return;
  }
  if (file.name.toLowerCase().endsWith(".txt")) {
    file.text().then((text) => {
      if (!resumeText.value.trim()) {
        resumeText.value = text;
        showToast("TXT resume loaded into the editor.");
      }
      updateResumePreview(text, `Previewing ${file.name}`);
    });
    return;
  }

  if (!resumeText.value.trim()) {
    renderPreviewWaiting(file.name);
  } else {
    updateResumePreview(resumeText.value, `Previewing pasted text with ${file.name} attached`);
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

resumeText.addEventListener("input", () => {
  updateResumePreview();
});

previewButtons.forEach((button) => {
  button.addEventListener("click", () => {
    previewMode = button.dataset.previewMode;
    previewButtons.forEach((control) => {
      const active = control === button;
      control.classList.toggle("active", active);
      control.classList.toggle("btn-primary", active);
      control.classList.toggle("btn-outline-primary", !active);
      control.setAttribute("aria-selected", String(active));
    });
    updateResumePreview();
  });
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
    if (!resumeText.value.trim() && latestReport.resume_excerpt) {
      updateResumePreview(latestReport.resume_excerpt, "Previewing extracted resume excerpt");
    }
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
  submitButton.innerHTML = isBusy
    ? `<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Analyzing...`
    : `<i class="fas fa-magnifying-glass-chart mr-1"></i>Analyze Resume`;
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

function updateResumePreview(sourceText = resumeText.value, sourceLabel = "Live preview from resume text") {
  const text = cleanPreviewText(sourceText).trim();
  previewSource.textContent = text ? sourceLabel : "Live preview from resume text";
  updatePreviewStats(text);

  if (!text) {
    resumePreview.classList.remove("raw-mode");
    resumePreview.innerHTML = `
      <div class="preview-empty">
        <i class="fas fa-file-circle-plus" aria-hidden="true"></i>
        <h3>Resume preview will appear here</h3>
        <p>Paste resume text or load a TXT file to preview the document before scoring.</p>
      </div>
    `;
    return;
  }

  resumePreview.classList.toggle("raw-mode", previewMode === "raw");
  resumePreview.innerHTML = previewMode === "raw" ? renderRawPreview(text) : renderFormattedPreview(text);
}

function renderPreviewWaiting(fileName) {
  previewSource.textContent = `${fileName} selected`;
  updatePreviewStats("");
  resumePreview.classList.remove("raw-mode");
  resumePreview.innerHTML = `
    <div class="preview-empty">
      <i class="fas fa-file-import" aria-hidden="true"></i>
      <h3>Preview after extraction</h3>
      <p>Run analysis to extract this file and show an excerpt here.</p>
    </div>
  `;
}

function updatePreviewStats(text) {
  const words = text ? (text.match(/\b[\p{L}\p{N}_]+\b/gu) || []).length : 0;
  const lines = text ? text.split(/\r?\n/).filter((line) => line.trim()).length : 0;
  const sections = detectSections(text);

  previewWords.textContent = String(words);
  previewLines.textContent = String(lines);
  previewSectionsCount.textContent = String(sections.length);
  previewSectionChips.innerHTML = sections.length
    ? sections.map((section) => `<span>${escapeHtml(toTitle(section))}</span>`).join("")
    : `<span class="muted-chip">No sections yet</span>`;
}

function renderRawPreview(text) {
  return `<pre>${escapeHtml(text)}</pre>`;
}

function cleanPreviewText(text) {
  return String(text)
    .replace(/\uFFFD+/g, " ")
    .replace(/(^|\s)\?{3,}(?=\s|$|[A-Za-z])/g, " ")
    .split(/\r?\n/)
    .filter((line) => !isExtractionNoiseLine(line))
    .join("\n")
    .replace(/[ \t]{2,}/g, " ")
    .replace(/\n{4,}/g, "\n\n\n");
}

function renderFormattedPreview(text) {
  const lines = text.split(/\r?\n/);
  const firstContentIndex = lines.findIndex((line) => line.trim());
  const firstLine = firstContentIndex >= 0 ? lines[firstContentIndex].trim() : "";
  const remaining = firstContentIndex >= 0 ? lines.slice(firstContentIndex + 1) : lines;
  const contactLines = [];
  const bodyLines = [];

  remaining.forEach((line, index) => {
    const trimmed = line.trim();
    const isEarlyContact = index < 5 && /(@|linkedin\.com|github\.com|\+?\d[\d\s().-]{7,}|https?:\/\/)/i.test(trimmed);
    if (trimmed && isEarlyContact) {
      contactLines.push(trimmed);
      return;
    }
    bodyLines.push(line);
  });

  const body = bodyLines
    .map((line) => renderPreviewLine(line))
    .join("");

  return `
    <header class="preview-resume-header">
      <h3>${escapeHtml(firstLine || "Untitled Resume")}</h3>
      ${contactLines.length ? `<div>${contactLines.map((line) => `<span>${escapeHtml(line)}</span>`).join("")}</div>` : ""}
    </header>
    <div class="preview-resume-body">${body || `<p class="preview-muted">Add more resume content to build the preview.</p>`}</div>
  `;
}

function renderPreviewLine(line) {
  const trimmed = line.trim();
  if (!trimmed) {
    return `<div class="preview-space"></div>`;
  }

  if (isExtractionNoiseLine(trimmed)) {
    return "";
  }

  if (isSectionHeading(trimmed)) {
    return `<h4>${escapeHtml(trimmed.replace(/:$/, ""))}</h4>`;
  }

  if (/^[-*•·▪▫◦●○]\s+/.test(trimmed)) {
    return `<p class="preview-bullet"><span></span>${escapeHtml(trimmed.replace(/^[-*•·▪▫◦●○]\s+/, ""))}</p>`;
  }

  return `<p>${escapeHtml(trimmed)}</p>`;
}

function detectSections(text) {
  if (!text.trim()) return [];
  return Object.entries(sectionPatterns)
    .filter(([, pattern]) => pattern.test(text))
    .map(([name]) => name);
}

function isExtractionNoiseLine(line) {
  const trimmed = String(line).trim();
  if (!trimmed) return false;
  if (/^\d{1,2}$/.test(trimmed)) return true;
  return trimmed.length <= 12 && /^[^\p{L}\p{N}]+$/u.test(trimmed);
}

function isSectionHeading(line) {
  const normalized = line.replace(/:$/, "");
  if (normalized.length > 38) return false;
  return Object.values(sectionPatterns).some((pattern) => pattern.test(normalized));
}

function toTitle(value) {
  return value.replaceAll("_", " ").replace(/\b\w/g, (letter) => letter.toUpperCase());
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
updateResumePreview();
