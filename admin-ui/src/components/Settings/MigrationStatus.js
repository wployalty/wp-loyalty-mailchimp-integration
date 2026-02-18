import React from 'react';
import ShimmerLoading from "../Common/ShimmerLoading";
import { UiLabelContext } from "../../Context";

const MigrationStatus = ({ isConnected, settings, migrationStatus, migrationStatusLoading, fetchMigrationStatus, appState }) => {
    const labels = React.useContext(UiLabelContext);

    if (!isConnected || !settings.list_id || !migrationStatus || migrationStatus.state === 'no_list') {
        return null;
    }

    return (
        <div className="flex flex-col w-full mt-6 border border-card_border rounded-xl bg-gray-50 p-5">
            {/* Header Row */}
            <div className="flex justify-between items-start mb-4">
                <div>
                    <h5 className="text-dark font-semibold text-base">
                        {labels.settings?.migration_status_title || "Migration Status"}
                    </h5>
                    <p className="text-xs text-light mt-1">
                        {labels.settings?.migration_status_subtitle || "Sync progress for the selected Mailchimp list"}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    {/* Status Pill */}
                    <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${
                        migrationStatus.state === 'no_runs'
                            ? 'bg-gray-100 text-gray-700'
                            : migrationStatus.state === 'in_progress'
                                ? 'bg-yellow-100 text-yellow-700'
                                : migrationStatus.errored_operations > 0
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-green-100 text-green-700'
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
                        className={`p-1.5 rounded-md hover:bg-gray-200 transition-colors ${migrationStatusLoading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}
                        title={labels.settings?.migration_refresh_status || "Refresh status"}
                    >
                        <i className={`wlr wlrf-refresh text-sm text-gray-600 ${migrationStatusLoading ? 'animate-spin' : ''}`} />
                    </button>
                </div>
            </div>

            {migrationStatusLoading && !migrationStatus.batch_count ? (
                <div className="flex flex-col gap-y-3">
                    <ShimmerLoading height="h-4" width="w-full" />
                    <ShimmerLoading height="h-4" width="w-3/4" />
                </div>
            ) : migrationStatus.state === 'no_runs' ? (
                <p className="text-sm text-gray-500">
                    {labels.settings?.migration_no_runs_message || "No migrations have run for this list yet. Migrations will appear here once started."}
                </p>
            ) : (
                <React.Fragment>
                    {/* Metrics Row */}
                    <div className="grid grid-cols-4 gap-4 mb-4">
                        <div className="flex flex-col">
                            <span className="text-xs text-gray-500 mb-1">
                                {labels.settings?.migration_total_ops || "Total Operations"}
                            </span>
                            <span className="text-lg font-semibold text-dark">
                                {migrationStatus.total_operations.toLocaleString()}
                            </span>
                        </div>
                        <div className="flex flex-col">
                            <span className="text-xs text-gray-500 mb-1">
                                {labels.settings?.migration_success || "Success"}
                            </span>
                            <span className="text-lg font-semibold text-green-600">
                                {migrationStatus.success_operations.toLocaleString()}
                            </span>
                        </div>
                        <div className="flex flex-col">
                            <span className="text-xs text-gray-500 mb-1">
                                {labels.settings?.migration_failures || "Failures"}
                            </span>
                            <span className={`text-lg font-semibold ${migrationStatus.errored_operations > 0 ? 'text-red-600' : 'text-dark'}`}>
                                {migrationStatus.errored_operations.toLocaleString()}
                            </span>
                        </div>
                        <div className="flex flex-col">
                            <span className="text-xs text-gray-500 mb-1">
                                {labels.settings?.migration_batches || "Batches"}
                            </span>
                            <span className="text-lg font-semibold text-dark">
                                {migrationStatus.batch_count}
                            </span>
                        </div>
                    </div>

                    {/* Error Info */}
                    {migrationStatus.errored_operations > 0 ? (
                        <div className="flex flex-col gap-2">
                            <div className="flex items-center gap-2 text-sm">
                                <span className="text-red-600">
                                    {migrationStatus.errored_operations.toLocaleString()} {labels.settings?.migration_failed_ops || "failed operations detected."}
                                </span>
                            </div>

                            {/* CSV Processing Status */}
                            {migrationStatus.csv_processing_status === 'processing' && (
                                <div className="flex items-center gap-2 text-sm">
                                    <div className="flex items-center gap-2 text-yellow-600">
                                        <i className="wlr wlrf-refresh animate-spin text-sm" />
                                        <span>{labels.settings?.csv_processing_message || "Processing failed users CSV..."}</span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => fetchMigrationStatus()}
                                        disabled={migrationStatusLoading}
                                        className="px-3 py-1 text-xs bg-primary text-white rounded hover:bg-primary-dark disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        {labels.settings?.check_csv_status || "Check Status"}
                                    </button>
                                </div>
                            )}

                            {/* CSV Ready for Download */}
                            {migrationStatus.csv_processing_status === 'completed' && migrationStatus.failed_users_csv_path && (
                                <div className="flex items-center gap-2 text-sm">
                                    <span className="text-green-600">
                                        {labels.settings?.csv_ready_message || "CSV file ready for download."}
                                    </span>
                                    <a
                                        href={`${typeof wlmi_settings_form !== 'undefined' ? wlmi_settings_form.ajax_url : appState.ajax_url || ''}?action=wlmi_download_failed_users_csv&wlmi_nonce=${appState.settings_nonce}`}
                                        className="text-primary hover:underline"
                                    >
                                        {labels.settings?.migration_download_csv || "Download failed users CSV"}
                                    </a>
                                </div>
                            )}

                            {/* CSV Processing Failed */}
                            {migrationStatus.csv_processing_status === 'failed' && (
                                <div className="flex items-center gap-2 text-sm text-red-600">
                                    <span>{labels.settings?.csv_processing_failed || "CSV processing failed. Please try again."}</span>
                                </div>
                            )}

                            {/* Fallback to raw error file if CSV not processing/completed and no CSV path */}
                            {migrationStatus.csv_processing_status !== 'processing' &&
                                migrationStatus.csv_processing_status !== 'completed' &&
                                !migrationStatus.failed_users_csv_path &&
                                migrationStatus.first_error_file_url && (
                                    <div className="flex items-center gap-2 text-sm">
                                        <a
                                            href={migrationStatus.first_error_file_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-primary hover:underline"
                                        >
                                            {labels.settings?.migration_download_error_file || "Download error file"}
                                        </a>
                                    </div>
                                )}
                        </div>
                    ) : migrationStatus.state === 'completed' && (
                        <p className="text-sm text-gray-500">
                            {labels.settings?.migration_no_errors || "No migration errors detected."}
                        </p>
                    )}

                    {/* Last checked timestamp */}
                    {migrationStatus.last_checked_at && (
                        <p className="text-xs text-gray-400 mt-3">
                            {labels.settings?.migration_last_checked || "Last checked:"} {migrationStatus.last_checked_at}
                        </p>
                    )}
                </React.Fragment>
            )}
        </div>
    );
};

export default MigrationStatus;
