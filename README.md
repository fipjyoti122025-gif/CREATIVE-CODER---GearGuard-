# 🛡️ GearGuard – Maintenance Management System

GearGuard is a role-based maintenance management system built to help organizations track equipment, manage maintenance requests, and streamline technician workflows using a Kanban-style process.

This project is designed for hackathon and demo purposes, focusing on clean architecture, real-world ERP logic, and a professional admin–manager–technician workflow.

---

## 🚀 Features Overview

### 🔐 Role-Based Access
- **Admin** – Platform owner
- **Manager** – Company operations controller
- **Employee** – Raises maintenance requests
- **Technician** – Works on assigned maintenance tasks

---

## 👑 Admin Module

The Admin handles platform-level operations only.

### Admin Capabilities
- Create and manage companies
- Create company managers
- Activate / suspend companies
- View admin audit logs
- Platform overview dashboard

### Admin Pages
- Dashboard
- Companies
- Audit Logs

---

## 🧑‍💼 Manager Module

The Manager controls everything inside a company.

### Manager Capabilities
- Create and manage employees (including technicians)
- Add and manage equipment
- Assign technicians to maintenance requests
- Monitor maintenance workflow
- View manager activity logs

---

## 👨‍🔧 Technician Module

Technicians are implemented as employees with a technician role.

### Technician Capabilities
- View assigned maintenance requests
- Work using Kanban workflow
- Update request status
- Add work notes
- Log hours spent on tasks

---

## 🔄 Maintenance Workflow

1. Employee creates a maintenance request  
2. Manager assigns a technician  
3. Technician starts work  
4. Technician logs work and time  
5. Request moves to **Repaired** or **Scrap**

Workflow stages:
admin pages 
<img width="1919" height="895" alt="image" src="https://github.com/user-attachments/assets/377874a9-b55a-474a-8f97-74a5958ffd85" />
<img width="1880" height="930" alt="image" src="https://github.com/user-attachments/assets/622bffd4-c1d8-42bd-9a95-93108de40077" />


technician workflow kanaban 
<img width="1880" height="930" alt="image" src="https://github.com/user-attachments/assets/2e2edfe6-1874-4a4a-8d14-1036c1a9d4ad" />
![odoo](https://github.com/user-attachments/assets/6f3247ab-1141-46ac-9632-ccacea577d98)

