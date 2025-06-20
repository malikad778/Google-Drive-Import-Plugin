# Google Drive Video Importer

## Description

This WordPress plugin allows you to seamlessly import video files directly from your Google Drive into your WordPress Media Library. Streamline your content creation process by easily bringing your video assets from the cloud to your website.

## Features

*   **Effortless Google Drive Integration:** Connect your Google Drive account to your WordPress site with ease.
*   **Direct Video Import:** Import video files (e.g., MP4) directly into your WordPress Media Library.
*   **Admin Interface:** Manage your video imports through a user-friendly administration panel within WordPress.
*   **OAuth 2.0 Support:** Securely authenticate with Google Drive using OAuth 2.0.
*   **MIME Type Handling:** Automatically handles common video MIME types for smooth integration.

## Installation

1.  **Download the Plugin:** Download the `google-drive-video-importer.zip` file.
2.  **Upload via WordPress:**
    *   Navigate to `Plugins > Add New` in your WordPress admin dashboard.
    *   Click on the `Upload Plugin` button.
    *   Choose the `google-drive-video-importer.zip` file and click `Install Now`.
3.  **Activate the Plugin:** After installation, click `Activate Plugin`.

## Configuration

To configure the Google Drive Video Importer plugin, you will need to set up a Google API Project and obtain your Google Client ID and Client Secret.

1.  **Create a Google API Project:**
    *   Go to the [Google Cloud Console](https://console.cloud.google.com/).
    *   Create a new project or select an existing one.
    *   Navigate to `APIs & Services > Credentials`.
    *   Click `+ Create Credentials` and select `OAuth client ID`.
    *   Choose `Web application` as the Application type.
    *   **Authorized redirect URIs:** Add your WordPress admin URL followed by `/wp-admin/admin.php?page=gdvi-importer` (e.g., `https://yourwebsite.com/wp-admin/admin.php?page=gdvi-importer`).
    *   Click `Create`.
2.  **Obtain Client ID and Client Secret:**
    *   After creating the OAuth client ID, you will be provided with your **Client ID** and **Client Secret**.
    *   Copy these values.
3.  **Configure Plugin in WordPress:**
    *   In your WordPress admin dashboard, navigate to `Drive Video Import` (under the main menu).
    *   Enter your **Google Client ID** and **Google Client Secret** into the respective fields.
    *   Save Changes.
4.  **Authorize Google Drive Access:**
    *   Follow the on-screen prompts to authorize the plugin to access your Google Drive files.

## Usage

Once configured, you can start importing videos:

1.  Go to `Drive Video Import` in your WordPress admin menu.
2.  Browse your Google Drive files.
3.  Select the video files you wish to import.
4.  Click the `Import` button.

Your imported videos will appear in your WordPress Media Library.
