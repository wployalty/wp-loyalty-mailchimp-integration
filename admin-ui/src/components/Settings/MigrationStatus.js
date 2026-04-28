import React from 'react';
import ShimmerLoading from "../Common/ShimmerLoading";
import { UiLabelContext } from "../../Context";

const MigrationStatus = ({ isConnected, settings, migrationStatus, migrationStatusLoading, fetchMigrationStatus, appState }) => {
    const labels = React.useContext(UiLabelContext);

    if (!isConnected || !settings.list_id || !migrationStatus || migrationStatus.state === 'no_list') {
        return null;
    }

    return (
        <div className="wlmi-flex wlmi-flex-col wlmi-w-full wlmi-mt-6 wlmi-border wlmi-border-card_border wlmi-rounded-xl wlmi-bg-gray-50 wlmi-p-5">
            {/* Header Row */}
            <div className="wlmi-flex wlmi-justify-between wlmi-items-start wlmi-mb-4">
                <div>
                    <h5 className="wlmi-text-dark wlmi-font-semibold wlmi-text-base">
                        {labels.settings?.migration_status_title || "Migration Status"}
                    </h5>
                    <p className="wlmi-text-xs wlmi-text-light wlmi-mt-1">
                        {labels.settings?.migration_status_subtitle || "Sync progress for the selected Mailchimp list"}
                    </p>
                </div>
                <div className="wlmi-flex wlmi-items-center wlmi-gap-2">
                    {/* Status Pill */}
                    <span className={`wlmi-inline-flex wlmi-items-center wlmi-px-2.5 wlmi-py-1 wlmi-rounded-full wlmi-text-xs wlmi-font-medium ${
                        migrationStatus.state === 'no_runs'
                            ? 'wlmi-bg-gray-100 wlmi-text-gray-700'
                            : migrationStatus.state === 'in_progress'
                                ? 'wlmi-bg-yellow-100 wlmi-text-yellow-700'
                                : migrationStatus.errored_operations > 0
                                    ? 'wlmi-bg-red-100 wlmi-text-red-700'
                                    : 'wlmi-bg-green-100 wlmi-text-green-700'
                        }`}>
                        {migrationStatus.state === 'no_runs'
                            ? (labels.settings?.migration_state_no_runs || "No runs yet")
                            : migrationStatus.state === 'in_progress'
                                ? (labels.settings?.migration_state_in_progress || "In progress")
                                : migrationStatus.errored_operations > 0
                                    ? (labels.settings?.migration_state_completed_errors || "Completed with errors")
                                    : (labels.settings?.migration_state_completed || "Completed")}
                    </span>
                    {/* Refresh Button */}
                    <button
                        type="button"
                        onClick={() => fetchMigrationStatus()}
                        disabled={migrationStatusLoading}
                        className={`wlmi-p-1.5 wlmi-rounded-md hover:wlmi-bg-gray-200 wlmi-transition-colors ${
                            migrationStatusLoading ? 'wlmi-opacity-50 wlmi-cursor-not-allowed' : 'wlmi-cursor-pointer'
                        }`}
                        title={labels.settings?.migration_refresh_status || "Refresh status"}
                    >
                        <i className={`wlr wlrf-refresh wlmi-text-sm wlmi-text-gray-600 ${migrationStatusLoading ? 'wlmi-animate-spin' : ''}`} />
                    </button>
                </div>
            </div>

            {migrationStatusLoading && !migrationStatus.batch_count ? (
                <div className="wlmi-flex wlmi-flex-col wlmi-gap-y-3">
                    <ShimmerLoading height="wlmi-h-4" width="wlmi-w-full" />
                    <ShimmerLoading height="wlmi-h-4" width="wlmi-w-3/4" />
                </div>
            ) : migrationStatus.state === 'no_runs' ? (
                <p className="wlmi-text-sm wlmi-text-gray-500">
                    {labels.settings?.migration_no_runs_message || "No migrations have run for this list yet. Migrations will appear here once started."}
                </p>
            ) : (
                <React.Fragment>
                    {/* Metrics Row */}
                    <div className="wlmi-grid wlmi-grid-cols-4 wlmi-gap-4 wlmi-mb-4">
                        <div className="wlmi-flex wlmi-flex-col">
                            <span className="wlmi-text-xs wlmi-text-gray-500 wlmi-mb-1">
                                {labels.settings?.migration_total_ops || "Total Operations"}
                            </span>
                            <span className="wlmi-text-lg wlmi-font-semibold wlmi-text-dark">
                                {migrationStatus.total_operations.toLocaleString()}
                            </span>
                        </div>
                        <div className="wlmi-flex wlmi-flex-col">
                            <span className="wlmi-text-xs wlmi-text-gray-500 wlmi-mb-1">
                                {labels.settings?.migration_success || "Success"}
                            </span>
                            <span className="wlmi-text-lg wlmi-font-semibold wlmi-text-green-600">
                                {migrationStatus.success_operations.toLocaleString()}
                            </span>
                        </div>
                        <div className="wlmi-flex wlmi-flex-col">
                            <span className="wlmi-text-xs wlmi-text-gray-500 wlmi-mb-1">
                                {labels.settings?.migration_failures || "Failures"}
                            </span>
                            <span className={`wlmi-text-lg wlmi-font-semibold ${migrationStatus.errored_operations > 0 ? 'wlmi-text-red-600' : 'wlmi-text-dark'}`}>
                                {migrationStatus.errored_operations.toLocaleString()}
                            </span>
                        </div>
                        <div className="wlmi-flex wlmi-flex-col">
                            <span className="wlmi-text-xs wlmi-text-gray-500 wlmi-mb-1">
                                {labels.settings?.migration_batches || "Batches"}
                            </span>
                            <span className="wlmi-text-lg wlmi-font-semibold wlmi-text-dark">
                                {migrationStatus.batch_count}
                            </span>
                        </div>
                    </div>

                    {/* Error Info */}
                    {migrationStatus.errored_operations > 0 ? (
                        <div className="wlmi-flex wlmi-flex-col wlmi-gap-2">
                            <div className="wlmi-flex wlmi-items-center wlmi-gap-2 wlmi-text-sm">
                                <span className="wlmi-text-red-600">
                                    {migrationStatus.errored_operations.toLocaleString()} {labels.settings?.migration_failed_ops || "failed operations detected."}
                                </span>
                            </div>

                            {/* CSV Processing Status */}
                            {migrationStatus.csv_processing_status === 'processing' && (
                                <div className="wlmi-flex wlmi-items-center wlmi-gap-2 wlmi-text-sm">
                                    <div className="wlmi-flex wlmi-items-center wlmi-gap-2 wlmi-text-yellow-600">
                                        <i className="wlr wlrf-refresh wlmi-animate-spin wlmi-text-sm" />
                                        <span>{labels.settings?.csv_processing_message || "Processing failed users CSV..."}</span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => fetchMigrationStatus()}
                                        disabled={migrationStatusLoading}
                                        className="wlmi-px-3 wlmi-py-1 wlmi-text-xs wlmi-bg-primary wlmi-text-white wlmi-rounded hover:wlmi-bg-primary-dark disabled:wlmi-opacity-50 disabled:wlmi-cursor-not-allowed wlmi-transition-colors"
                                    >
                                        {labels.settings?.check_csv_status || "Check Status"}
                                    </button>
                                </div>
                            )}

                            {/* CSV Ready for Download */}
                            {migrationStatus.csv_processing_status === 'completed' && (migrationStatus.failed_users_csv_url || appState.ajax_url) && (
                                <div className="wlmi-flex wlmi-items-center wlmi-gap-2 wlmi-text-sm">
                                    <span className="wlmi-text-green-600">
                                        {labels.settings?.csv_ready_message || "CSV file ready for download."}
                                    </span>
                                    <a
                                        href={migrationStatus.failed_users_csv_url || `${typeof wlmi_settings_form !== 'undefined' ? wlmi_settings_form.ajax_url : appState.ajax_url || ''}?action=wlmi_download_failed_users_csv&wlmi_nonce=${appState.settings_nonce}`}
                                        className="wlmi-text-primary hover:wlmi-underline"
                                    >
                                        {labels.settings?.migration_download_csv || "Download failed users CSV"}
                                    </a>
                                </div>
                            )}

                            {/* CSV Processing Failed */}
                            {migrationStatus.csv_processing_status === 'failed' && (
                                <div className="wlmi-flex wlmi-items-center wlmi-gap-2 wlmi-text-sm wlmi-text-red-600">
                                    <span>{labels.settings?.csv_processing_failed || "CSV processing failed. Please try again."}</span>
                                </div>
                            )}

                            {/* Fallback to raw error file if CSV not processing/completed and no CSV path */}
                            {migrationStatus.csv_processing_status !== 'processing' &&
                                migrationStatus.csv_processing_status !== 'completed' &&
                                !migrationStatus.failed_users_csv_url &&
                                migrationStatus.first_error_file_url && (
                                    <div className="wlmi-flex wlmi-items-center wlmi-gap-2 wlmi-text-sm">
                                        <a
                                            href={migrationStatus.first_error_file_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="wlmi-text-primary hover:wlmi-underline"
                                        >
                                            {labels.settings?.migration_download_error_file || "Download error file"}
                                        </a>
                                    </div>
                                )}
                        </div>
                    ) : migrationStatus.state === 'completed' && (
                        <p className="wlmi-text-sm wlmi-text-gray-500">
                            {labels.settings?.migration_no_errors || "No migration errors detected."}
                        </p>
                    )}

                    {/* Last checked timestamp */}
                    {migrationStatus.last_checked_at && (
                        <p className="wlmi-text-xs wlmi-text-gray-400 wlmi-mt-3">
                            {labels.settings?.migration_last_checked || "Last checked:"} {migrationStatus.last_checked_at}
                        </p>
                    )}
                </React.Fragment>
            )}
        </div>
    );
};

export default MigrationStatus;
