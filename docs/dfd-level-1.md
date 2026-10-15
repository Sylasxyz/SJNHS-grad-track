# DFD Level 1 — Graduate Tracking System

Decomposition of process **0 Graduate Tracking System** (matches use case modules; three actors: Student, Instructor, Admin).

## Sub-processes

| ID | Process name | Use case module |
|----|----------------|-----------------|
| **1.0** | Register and Validate Account | User Account Management |
| **2.0** | Manage Graduate Profile | Graduate Profile Management |
| **3.0** | Manage Communication | Communication Management + Announcement Management |
| **4.0** | Manage Learner Accounts | Learner Account Management |
| **5.0** | Administer User Accounts | User Administration |
| **6.0** | Manage Graduate Records | Graduate Records Management |
| **7.0** | Provide Role-Based Dashboard | Dashboard Management |
| **8.0** | Analytics and Report Generation | Analytics and Report Generation |

## Data stores

| ID | Data store | Table (implementation) |
|----|------------|-------------------------|
| **D1** | User Accounts | User_Accounts |
| **D2** | Graduate Profiles | Graduate_Profiles |
| **D3** | Announcements | Announcements |
| **D4** | Messages | User_Messages |
| **D5** | Saved Reports | Reports |

---

## External ↔ sub-process (list format)

### Student

- Student — Register Account Data → **1.0 Register and Validate Account**
- Student — Validate Registration Data → **1.0 Register and Validate Account**
- **1.0 Register and Validate Account** — Registration Validation Result → Student
- Student — Manage Own Graduate Profile → **2.0 Manage Graduate Profile**
- Student — Upload Remove Profile Picture → **2.0 Manage Graduate Profile**
- Student — Employment and Contact Info → **2.0 Manage Graduate Profile**
- **2.0 Manage Graduate Profile** — Profile Verification Status → Student
- Student — Send Receive Messages → **3.0 Manage Communication**
- **3.0 Manage Communication** — View Announcement → Student
- **3.0 Manage Communication** — Send Receive Messages → Student

### Instructor

- Instructor — Approve Pending Student Account → **4.0 Manage Learner Accounts**
- Instructor — Manage Students Account → **4.0 Manage Learner Accounts**
- **4.0 Manage Learner Accounts** — Pending Student List → Instructor
- Instructor — Search Filter Graduates → **6.0 Manage Graduate Records**
- Instructor — Verify Graduate Profiles → **6.0 Manage Graduate Records**
- Instructor — Edit Graduate Record → **6.0 Manage Graduate Records**
- Instructor — Export Graduate CSV → **6.0 Manage Graduate Records**
- **6.0 Manage Graduate Records** — Graduate Records → Instructor
- **6.0 Manage Graduate Records** — Export Graduate CSV → Instructor
- Instructor — View Role-Based Dashboard → **7.0 Provide Role-Based Dashboard**
- **7.0 Provide Role-Based Dashboard** — View Role-Based Dashboard → Instructor
- Instructor — View Archived Announcement → **3.0 Manage Communication**
- Instructor — Post Announcement → **3.0 Manage Communication**
- Instructor — Attach Image to Announcement → **3.0 Manage Communication**
- Instructor — Archive Restore Announcement → **3.0 Manage Communication**
- **3.0 Manage Communication** — View Announcement → Instructor
- **3.0 Manage Communication** — View Archived Announcement → Instructor
- Instructor — Send Receive Messages → **3.0 Manage Communication**
- **3.0 Manage Communication** — Send Receive Messages → Instructor

### Admin

- Admin — Approve Reject Registration → **5.0 Administer User Accounts**
- Admin — Create User Account → **5.0 Administer User Accounts**
- Admin — Deactivate User Accounts → **5.0 Administer User Accounts**
- Admin — Edit User and Role → **5.0 Administer User Accounts**
- **5.0 Administer User Accounts** — User Account Data → Admin
- Admin — Delete Graduate Records → **6.0 Manage Graduate Records**
- **6.0 Manage Graduate Records** — Delete Graduate Records Result → Admin
- Admin — Permanently Delete Announcement → **3.0 Manage Communication**
- Admin — View Role-Based Dashboard → **7.0 Provide Role-Based Dashboard**
- **7.0 Provide Role-Based Dashboard** — View Role-Based Dashboard → Admin
- Admin — View Analytics → **8.0 Analytics and Report Generation**
- Admin — Save Analytics → **8.0 Analytics and Report Generation**
- Admin — Generate Employment Report → **8.0 Analytics and Report Generation**
- Admin — Compute Employment Statistics → **8.0 Analytics and Report Generation**
- Admin — View Saved Report → **8.0 Analytics and Report Generation**
- Admin — Delete Saved Report → **8.0 Analytics and Report Generation**
- Admin — Export Saved Report CSV → **8.0 Analytics and Report Generation**
- **8.0 Analytics and Report Generation** — View Analytics → Admin
- **8.0 Analytics and Report Generation** — Generate Employment Report → Admin
- **8.0 Analytics and Report Generation** — Compute Employment Statistics → Admin
- **8.0 Analytics and Report Generation** — View Saved Report → Admin
- **8.0 Analytics and Report Generation** — Export Saved Report CSV → Admin

*(Admin also shares Instructor flows to **3.0**, **6.0**, **7.0** where your use case shows Admin on those modules.)*

---

## Sub-process ↔ data store

- **1.0** — User registration record → **D1 User Accounts**
- **2.0** — Profile read/write → **D2 Graduate Profiles**
- **2.0** — Student user link → **D1 User Accounts**
- **3.0** — Announcement read/write → **D3 Announcements**
- **3.0** — Message read/write → **D4 Messages**
- **4.0** — Student status update → **D1 User Accounts**
- **5.0** — User CRUD → **D1 User Accounts**
- **6.0** — Graduate record read/write → **D2 Graduate Profiles**
- **6.0** — Account lookup → **D1 User Accounts**
- **7.0** — Summary read → **D1**, **D2**, **D3**, **D5** *(aggregated dashboard)*
- **8.0** — Statistics read → **D2 Graduate Profiles**
- **8.0** — Report save/read → **D5 Saved Reports**

---

## Internal flows (between processes)

- **1.0** — New student account ID → **2.0** *(profile created after approval/active)*
- **4.0** — Student approved → **1.0** / **D1** status Active
- **5.0** — Registration approved → **D1**
- **6.0** — Verification update → **D2** → **7.0** / **8.0** *(updated stats)*

---

## draw.io layout hint

- Center: broken **0** into **1.0–8.0** bubbles (two rows of four, or circle around inner processes).
- **D1–D5** as open rectangles on the sides.
- **Student** bottom-left, **Instructor** top-right, **Admin** bottom-right (same as Level 0).
- Label arrows using the list lines above.
