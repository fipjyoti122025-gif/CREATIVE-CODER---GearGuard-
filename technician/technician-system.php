<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Technician Workspace - Glass Kanban</title>

<style>
/* ============================= */
/* GLOBAL RESET */
/* ============================= */
*{
  margin:0;
  padding:0;
  box-sizing:border-box;
  font-family:"Poppins", sans-serif;
}

body{
  background: linear-gradient(135deg, #0a1a34, #0f2b4d, #1a3d5f);
  padding:30px;
  color:white;
}

/* ============================= */
/* PAGE TITLE */
/* ============================= */
h1{
  font-size:34px;
  font-weight:700;
  margin-bottom:25px;
  color:#EAF3FF;
}

/* ============================= */
/* SUMMARY CARDS */
/* ============================= */
.summary-container{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:20px;
  margin-bottom:35px;
}

.summary-card{
  backdrop-filter: blur(18px);
  background: rgba(255,255,255,0.12);
  border:1px solid rgba(255,255,255,0.25);
  padding:20px;
  border-radius:15px;
  box-shadow:0 8px 25px rgba(0,0,0,0.25);
}

.summary-card h2{
  font-size:14px;
  color:#CDE3FF;
}

.summary-card p{
  font-size:32px;
  font-weight:600;
  margin-top:10px;
  color:#fff;
}

/* ============================= */
/* KANBAN BOARD */
/* ============================= */
.kanban-board{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:25px;
  margin-top:20px;
}

/* Columns */
.kanban-column{
  backdrop-filter: blur(20px);
  background: rgba(255,255,255,0.10);
  border:1px solid rgba(255,255,255,0.25);
  border-radius:16px;
  padding:18px;
  height:72vh;
  overflow-y:auto;
  box-shadow:0px 10px 25px rgba(0,0,0,0.22);
  transition:0.3s;
}

.kanban-column:hover{
  background: rgba(255,255,255,0.18);
}

.kanban-title{
  font-size:18px;
  font-weight:700;
  margin-bottom:12px;
  color:#EAF3FF;
  text-shadow:0 2px 8px rgba(0,0,0,0.6);
}

/* ============================= */
/* GLASSMORPHIC TASK CARDS */
/* ============================= */
.task-card{
  background: rgba(255,255,255,0.15);
  border:1px solid rgba(255,255,255,0.25);
  backdrop-filter: blur(14px);
  padding:14px;
  border-radius:12px;
  margin-bottom:15px;
  box-shadow:0px 6px 20px rgba(0,0,0,0.25);
  cursor:pointer;
  transition:0.25s ease;
}

.task-card:hover{
  transform:translateY(-4px);
  background: rgba(255,255,255,0.23);
  border-color: rgba(255,255,255,0.40);
}

/* Card Content */
.task-title{
  font-size:16px;
  font-weight:600;
  color:#fff;
}

.task-info{
  font-size:13px;
  margin-top:4px;
  color:#E0ECFF;
}

/* Priority badge */
.priority-badge{
  padding:5px 10px;
  border-radius:10px;
  font-size:12px;
  font-weight:600;
  margin-top:10px;
  display:inline-block;
  color:white;
  text-shadow:0px 1px 4px rgba(0,0,0,0.5);
}

/* Priority Colors */
.low{ background:#1abc9c; }
.medium{ background:#f1c40f; color:black; }
.high{ background:#e67e22; }
.critical{ background:#e74c3c; }

/* ============================= */
/* MODAL (TASK DETAIL) */
/* ============================= */
.modal-bg{
  position:fixed;
  top:0;left:0;
  width:100%;height:100%;
  background:rgba(0,0,0,0.6);
  display:none;
  justify-content:center;
  align-items:center;
  z-index:999;
}

.modal-card{
  width:520px;
  backdrop-filter: blur(18px);
  background: rgba(255,255,255,0.18);
  padding:25px;
  border-radius:18px;
  box-shadow:0px 15px 40px rgba(0,0,0,0.35);
  border:1px solid rgba(255,255,255,0.35);
}

.close-btn{
  float:right;
  background:#e74c3c;
  color:white;
  padding:6px 12px;
  border:none;
  border-radius:8px;
  cursor:pointer;
  font-weight:600;
}

.modal-card h2{
  font-size:22px;
  font-weight:700;
  margin-bottom:15px;
  color:white;
}

.modal-card label{
  font-weight:600;
  margin-top:10px;
  display:block;
  color:#EAF3FF;
}

.modal-card input,
.modal-card textarea{
  width:100%;
  padding:12px;
  border-radius:10px;
  border:none;
  margin-top:5px;
  background:rgba(255,255,255,0.35);
  color:#000;
}

/* Modal action buttons */
.btn-group{
  display:flex;
  justify-content:space-between;
  margin-top:20px;
}

.modal-btn{
  flex:1;
  padding:12px;
  margin-right:10px;
  border-radius:12px;
  border:none;
  cursor:pointer;
  font-weight:700;
  color:white;
  transition:0.2s;
}

.modal-btn:last-child{
  margin-right:0;
}

.start-btn{background:#3498db;}
.progress-btn2{background:#f1c40f;color:black;}
.complete-btn{background:#2ecc71;}
.scrap-btn{background:#e74c3c;}

/* ============================= */
/* WORK LOGS */
/* ============================= */
.logs-section{
  margin-top:40px;
}

.logs-section h2{
  font-size:24px;
  color:#EAF3FF;
  margin-bottom:15px;
}

.logs-table{
  width:100%;
  border-collapse:collapse;
  background:rgba(255,255,255,0.14);
  backdrop-filter:blur(18px);
  border-radius:18px;
  overflow:hidden;
  color:white;
}

.logs-table th{
  background:rgba(255,255,255,0.25);
  padding:12px;
  border-bottom:1px solid rgba(255,255,255,0.3);
}

.logs-table td{
  padding:12px;
  border-bottom:1px solid rgba(255,255,255,0.15);
}
</style>
</head>

<body>

<h1>Technician Workspace</h1>

<div class="summary-container">
  <div class="summary-card"><h2>Assigned Tasks</h2><p id="assignedCount">0</p></div>
  <div class="summary-card"><h2>In Progress</h2><p id="progressCount">0</p></div>
  <div class="summary-card"><h2>Repaired</h2><p id="repairedCount">0</p></div>
  <div class="summary-card"><h2>Scrap</h2><p id="scrapCount">0</p></div>
</div>

<div class="kanban-board">
  <div class="kanban-column" id="newColBox">
    <div class="kanban-title">New</div>
    <div class="kanban-body" id="newCol"></div>
  </div>

  <div class="kanban-column" id="progressColBox">
    <div class="kanban-title">In Progress</div>
    <div class="kanban-body" id="progressCol"></div>
  </div>

  <div class="kanban-column" id="repairedColBox">
    <div class="kanban-title">Repaired</div>
    <div class="kanban-body" id="repairedCol"></div>
  </div>

  <div class="kanban-column" id="scrapColBox">
    <div class="kanban-title">Scrap</div>
    <div class="kanban-body" id="scrapCol"></div>
  </div>
</div>

<!-- MODAL -->
<div class="modal-bg" id="taskModal">
  <div class="modal-card">
    <button class="close-btn" onclick="closeTaskModal()">X</button>
    <h2 id="modalTitle">Task Details</h2>

    <label>Equipment</label>
    <input id="modalEquipment" type="text" readonly>

    <label>Issue</label>
    <textarea id="modalIssue" readonly></textarea>

    <label>Priority</label>
    <input id="modalPriority" type="text" readonly>

    <label>Status</label>
    <input id="modalStatus" type="text" readonly>

    <label>Work Notes</label>
    <textarea id="modalNotes"></textarea>

    <label>Hours Spent</label>
    <input id="modalHours" type="number" step="0.5">

    <div class="btn-group">
      <button class="modal-btn start-btn" onclick="moveToProgress()">Start</button>
      <button class="modal-btn complete-btn" onclick="markRepaired()">Complete</button>
      <button class="modal-btn scrap-btn" onclick="moveToScrap()">Scrap</button>
    </div>
  </div>
</div>

<div class="logs-section">
  <h2>Work Logs</h2>
  <table class="logs-table">
    <thead>
      <tr>
        <th>Request</th>
        <th>Notes</th>
        <th>Hours</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody id="logsBody"></tbody>
  </table>
</div>
<style>

/* ============================= */
/* GLASS ANIMATIONS */
/* ============================= */

/* Card drag animation */
.task-card.dragging {
  opacity: 0.35;
  transform: scale(0.96);
  border: 1px dashed rgba(255,255,255,0.5);
}

/* Drop target highlight */
.kanban-body.drag-over {
  background: rgba(255,255,255,0.18);
  border: 2px dashed rgba(255,255,255,0.4);
  border-radius: 14px;
  transition: 0.25s;
}

/* Smooth column fade-in effect when items change */
.kanban-body {
  transition: background 0.25s, border 0.25s;
}

/* Card hover glow */
.task-card:hover {
  box-shadow: 0 12px 25px rgba(255,255,255,0.12), 
              0 6px 12px rgba(0,0,0,0.25);
}

/* Column Glow on hover */
.kanban-column {
  transition: 0.3s ease;
}
.kanban-column:hover {
  transform: translateY(-3px);
  box-shadow: 0 14px 35px rgba(0,0,0,0.4);
}

/* Modal fade-in animation */
@keyframes fadeIn {
  from {opacity: 0; transform: scale(0.92);}
  to   {opacity: 1; transform: scale(1);}
}

.modal-card {
  animation: fadeIn 0.3s ease;
}

/* Logs table rows hover */
.logs-table tr:hover td {
  background: rgba(255,255,255,0.25);
  cursor: pointer;
  transition: 0.2s;
}

/* Scrollbar styling */
.kanban-column::-webkit-scrollbar,
.logs-section::-webkit-scrollbar {
  width: 8px;
}
.kanban-column::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,0.35);
  border-radius: 10px;
}
.kanban-column::-webkit-scrollbar-track {
  background: rgba(255,255,255,0.1);
}

/* Card entrance animation */
@keyframes cardEnter {
  from {opacity: 0; transform: translateY(8px);}
  to   {opacity: 1; transform: translateY(0);}
}

.task-card {
  animation: cardEnter 0.3s ease;
}

/* Title glow */
h1 {
  text-shadow: 0 4px 18px rgba(0,0,0,0.5);
}

.kanban-title {
  text-shadow: 0 3px 12px rgba(0,0,0,0.6);
}

/* Button hover animation */
.modal-btn:hover {
  transform: scale(1.04);
  box-shadow: 0 0 12px rgba(255,255,255,0.4);
}

/* Vibrance glow for action buttons */
.start-btn:hover { box-shadow: 0 0 12px #3498db; }
.complete-btn:hover { box-shadow: 0 0 12px #2ecc71; }
.scrap-btn:hover { box-shadow: 0 0 12px #e74c3c; }

/* Dashboard card animation */
.summary-card {
  animation: fadeInSummary 0.6s ease forwards;
  opacity: 0;
}

@keyframes fadeInSummary {
  from {opacity:0; transform:translateY(20px);}
  to {opacity:1; transform:translateY(0);}
}

/* Responsive support */
@media(max-width:1200px){
  .kanban-board{
    grid-template-columns:repeat(2,1fr);
  }
}
@media(max-width:768px){
  .kanban-board{
    grid-template-columns:1fr;
  }
  .modal-card{
    width:90%;
  }
  .summary-container{
    grid-template-columns:repeat(2,1fr);
  }
}

</style>
<script>
/* ===========================================================
   DUMMY TASK DATA (Replace later with PHP + MySQL)
=========================================================== */
let tasks = [
  {
    id:"REQ-201",
    equipment:"Forklift",
    issue:"Hydraulic leakage",
    priority:"high",
    status:"new",
    notes:"",
    hours:0
  },
  {
    id:"REQ-202",
    equipment:"Desktop PC",
    issue:"No Display",
    priority:"medium",
    status:"in_progress",
    notes:"",
    hours:1
  },
  {
    id:"REQ-203",
    equipment:"Boiler Pump",
    issue:"Motor overheating",
    priority:"critical",
    status:"new",
    notes:"",
    hours:0
  },
  {
    id:"REQ-204",
    equipment:"CCTV Camera",
    issue:"Lens issue",
    priority:"low",
    status:"repaired",
    notes:"Lens cleaned & adjusted",
    hours:1
  },
  {
    id:"REQ-205",
    equipment:"UPS Battery",
    issue:"Backup failure",
    priority:"high",
    status:"scrap",
    notes:"Battery damaged",
    hours:2
  }
];


/* ===========================================================
   RENDER KANBAN BOARD
=========================================================== */
function renderKanban(){
  // Clear columns
  newCol.innerHTML = "";
  progressCol.innerHTML = "";
  repairedCol.innerHTML = "";
  scrapCol.innerHTML = "";

  tasks.forEach(task => {
    
    let card = document.createElement("div");
    card.className = "task-card";
    card.draggable = true;
    card.dataset.id = task.id;

    card.innerHTML = `
      <div class="task-title">${task.equipment}</div>
      <div class="task-info">${task.issue}</div>
      <span class="priority-badge ${task.priority}">${task.priority}</span>
    `;

    // Open modal on click
    card.addEventListener("click", e => {
      e.stopPropagation();
      openTaskModal(task.id);
    });

    // Drag events
    card.addEventListener("dragstart", dragStart);
    card.addEventListener("dragend", dragEnd);

    // Append to correct column
    if(task.status === "new") newCol.appendChild(card);
    if(task.status === "in_progress") progressCol.appendChild(card);
    if(task.status === "repaired") repairedCol.appendChild(card);
    if(task.status === "scrap") scrapCol.appendChild(card);
  });

  updateDashboard();
}


/* ===========================================================
   DRAG & DROP LOGIC
=========================================================== */
let draggedCard = null;

function dragStart(e){
  draggedCard = e.target;
  draggedCard.classList.add("dragging");
}

function dragEnd(e){
  draggedCard.classList.remove("dragging");
  draggedCard = null;
}

document.querySelectorAll(".kanban-body").forEach(col=>{
  
  col.addEventListener("dragover", e => {
    e.preventDefault();
    col.classList.add("drag-over");
  });

  col.addEventListener("dragleave", () => {
    col.classList.remove("drag-over");
  });

  col.addEventListener("drop", () => {
    col.classList.remove("drag-over");

    let id = draggedCard.dataset.id;

    let newStatus =
      col.id === "newCol" ? "new" :
      col.id === "progressCol" ? "in_progress" :
      col.id === "repairedCol" ? "repaired" :
      "scrap";

    tasks.forEach(t => {
      if(t.id === id) t.status = newStatus;
    });

    renderKanban();
  });
});


/* ===========================================================
   OPEN TASK MODAL
=========================================================== */
let activeTask = null;

function openTaskModal(taskId){
  activeTask = tasks.find(t => t.id === taskId);

  modalTitle.innerText = `Task ${activeTask.id}`;
  modalEquipment.value = activeTask.equipment;
  modalIssue.value = activeTask.issue;
  modalPriority.value = activeTask.priority;
  modalStatus.value = activeTask.status;
  modalNotes.value = activeTask.notes;
  modalHours.value = activeTask.hours;

  // Button visibility based on status
  document.querySelector(".start-btn").style.display =
    activeTask.status === "new" ? "block" : "none";

  document.querySelector(".complete-btn").style.display =
    activeTask.status === "in_progress" ? "block" : "none";

  document.querySelector(".scrap-btn").style.display =
    activeTask.status === "scrap" ? "none" : "block";

  taskModal.style.display = "flex";
}

function closeTaskModal(){
  taskModal.style.display = "none";
}


/* ===========================================================
   BUTTON ACTIONS
=========================================================== */
function moveToProgress(){
  activeTask.status = "in_progress";
  saveWorkLog("Started work");
  closeTaskModal();
  renderKanban();
}

function markRepaired(){
  activeTask.status = "repaired";
  saveWorkLog("Repaired successfully");
  closeTaskModal();
  renderKanban();
}

function moveToScrap(){
  activeTask.status = "scrap";
  saveWorkLog("Marked as scrap");
  closeTaskModal();
  renderKanban();
}


/* ===========================================================
   SAVE WORK LOG
=========================================================== */
function saveWorkLog(action){
  activeTask.notes = modalNotes.value;
  activeTask.hours = modalHours.value;

  let row = document.createElement("tr");

  row.innerHTML = `
    <td>${activeTask.id}</td>
    <td>${modalNotes.value || "—"}</td>
    <td>${modalHours.value || 0}</td>
    <td>${new Date().toISOString().split("T")[0]}</td>
  `;

  logsBody.appendChild(row);
}


/* ===========================================================
   UPDATE DASHBOARD COUNTS
=========================================================== */
function updateDashboard(){
  assignedCount.innerText = tasks.filter(t => t.status === "new").length;
  progressCount.innerText = tasks.filter(t => t.status === "in_progress").length;
  repairedCount.innerText = tasks.filter(t => t.status === "repaired").length;
  scrapCount.innerText = tasks.filter(t => t.status === "scrap").length;
}


/* ===========================================================
   INIT SYSTEM
=========================================================== */
renderKanban();

</script>

</body>
</html>
