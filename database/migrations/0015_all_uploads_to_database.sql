-- =====================================================================
-- Migration: Store KYC Documents as BLOB in Database
-- Date: May 11, 2026
-- Purpose: Move all file uploads from file system to database
-- =====================================================================

-- Add BLOB columns to member_documents table if they don't exist
ALTER TABLE member_documents
ADD COLUMN file_content LONGBLOB COMMENT 'File content stored as BLOB' AFTER file_path,
ADD COLUMN file_type VARCHAR(100) COMMENT 'MIME type (image/jpeg, application/pdf, etc)' AFTER file_content,
ADD COLUMN original_filename VARCHAR(255) COMMENT 'Original uploaded filename' AFTER file_type;

-- Create index for faster queries
ALTER TABLE member_documents ADD INDEX idx_member_doc_type (member_id, document_type);

-- =====================================================================
-- Admin Profile Pictures - Already using BLOB storage
-- =====================================================================
-- Profile pictures in admins table already stored as BLOB in profile_pic column
-- No changes needed

-- =====================================================================
-- Member Profile Data - Already using BLOB storage
-- =====================================================================
-- Member profile pictures, if any, should also use BLOB in members table
-- Ensure members table has profile_picture column as LONGBLOB
ALTER TABLE members 
ADD COLUMN IF NOT EXISTS profile_picture LONGBLOB COMMENT 'Member profile picture' AFTER kyc_status;

-- =====================================================================
-- Summary of Database-Backed Storage
-- =====================================================================
/*
All user uploads now stored in database:

1. Admin Profile Pictures
   Table: admins
   Column: profile_pic (LONGBLOB)
   
2. Member Profile Pictures  
   Table: members
   Column: profile_picture (LONGBLOB)
   
3. KYC Documents (National ID, Passport, etc)
   Table: member_documents
   Column: file_content (LONGBLOB)
   Column: file_type (VARCHAR) - MIME type
   Column: original_filename (VARCHAR) - Original filename
   
Benefits:
✅ No file system dependencies
✅ Automatic database backups
✅ Works across multiple servers
✅ Better security (no direct file access)
✅ Easier to implement versioning
✅ GDPR-compliant data deletion
*/
