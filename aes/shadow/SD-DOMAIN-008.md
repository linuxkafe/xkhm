# SD-DOMAIN-008 — WHMCS knowledgebase structure: categories + articles with published flag

- **Insight**: WHMCS knowledgebase uses `tblknowledgebasecats` (categories with sortorder) and `tblknowledgebase` (articles with published flag). Articles linked by `categoryid`. Search via LIKE on title and description. Announcements in `tblannouncements` with `published` field for live/draft status.
- **Impact**: KnowledgebaseService provides category browsing and article search. AnnouncementService provides simple published announcement listing.
- **Applied To**: T010 knowledgebase and announcements. Future admin features will add CRUD operations.
- **Date**: 2026-10-20