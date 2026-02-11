import React from 'react';
import TitleActionContainer from "../components/Common/TitleActionContainer";
import ShimmerLoading from "../components/Common/ShimmerLoading";
import ListSelect from "../components/Common/ListSelect";
import { CommonContext, UiLabelContext } from "../Context";
import { postRequest } from "../components/Common/postRequest";
import { alertifyToast, errorDisplayer, getJSONData, getChosenLabel } from "../helpers/utilities";
import Input from "../components/Common/Input";
import Button from "../components/Common/Button";
import DropdownWrapper from "../components/Common/DropdownWrapper";
import EmptyPage from "../components/Common/EmptyPage";

const Settings = () => {
    const {appState} = React.useContext(CommonContext);
    const labels = React.useContext(UiLabelContext);

    const [settings, setSettings] = React.useState({
        api_key: "",
        list_id: "",
        migration_choice: ""
    });
    const [loading, setLoading] = React.useState(true);
    const [testLoading, setTestLoading] = React.useState(false);
    const [disableSave, setDisableSave] = React.useState(false);
    const [errorList, setErrorList] = React.useState([]);
    const [errors, setErrors] = React.useState({});
    const [isConnected, setIsConnected] = React.useState(false);
    const [settingsSaved, setSettingsSaved] = React.useState(false);
    const [licenseStatus, setLicenseStatus] = React.useState("inactive");

    // List selection state
    const [lists, setLists] = React.useState([]);
    const [listsLoading, setListsLoading] = React.useState(false);
    const [nextOffset, setNextOffset] = React.useState(0);
    const [totalLists, setTotalLists] = React.useState(0);
    const [selectedList, setSelectedList] = React.useState(null);
    const [currentSearchTerm, setCurrentSearchTerm] = React.useState('');
    const [isAutoFetching, setIsAutoFetching] = React.useState(false);
    const searchingForListIdRef = React.useRef(null);
    const listSearchBatchCountRef = React.useRef(0);

    // Migration status state
    const [migrationStatus, setMigrationStatus] = React.useState(null);
    const [migrationStatusLoading, setMigrationStatusLoading] = React.useState(false);

    /**
     * Fetch migration status from the backend
     */
    const fetchMigrationStatus = async () => {
        setMigrationStatusLoading(true);
        try {
            const params = {
                action: "wlmi_get_migration_status",
                wlmi_nonce: appState.settings_nonce,
            };
            const json = await postRequest(params);
            const resJSON = getJSONData(json.data);
            if (resJSON.success === true && resJSON.data) {
                setMigrationStatus(resJSON.data);
            }
        } catch (e) {
            // Silent fail, keep existing status
        } finally {
            setMigrationStatusLoading(false);
        }
    };

    const getSettings = async (wlmi_nonce = appState.settings_nonce) => {
        setLoading(true);
        let params = {
            action: "wlmi_launcher_settings",
            wlmi_nonce,
        };
        
        try {
            const json = await postRequest(params);
            let resJSON = getJSONData(json.data);
            if (resJSON.success === true && resJSON.data !== null) {
                 let loadedSettings = resJSON.data;
                 if (!loadedSettings.api_key) loadedSettings.api_key = ""; 
                if (!loadedSettings.list_id) loadedSettings.list_id = "";
                if (!loadedSettings.migration_choice) loadedSettings.migration_choice = "";
                if (typeof loadedSettings.wlmi_request_migration_from_admin === 'undefined') {
                    loadedSettings.wlmi_request_migration_from_admin = false;
                }
                 setSettings(loadedSettings);
                setLicenseStatus(loadedSettings.license_status || "inactive");
                setIsConnected(loadedSettings.connected || false);
                // Only mark as saved if API key actually exists in saved settings
                // This prevents showing list selector when settings are deleted but defaults are returned
                const hasSavedApiKey = loadedSettings.api_key && loadedSettings.api_key.trim() !== "";
                setSettingsSaved(hasSavedApiKey);

                // If connected and settings are saved, fetch initial lists
                if (loadedSettings.connected && hasSavedApiKey) {
                    fetchLists('', 0, true, false, loadedSettings.list_id);
                }

                // If list_id is configured, fetch migration status
                if (loadedSettings.list_id && loadedSettings.list_id.trim() !== "") {
                    fetchMigrationStatus();
                }
            } else {
                // No settings found, mark as not saved
                setSettingsSaved(false);
            }
        } catch (e) {
            // Handle error
        } finally {
            setLoading(false);
        }
    }

    React.useEffect(() => {
        getSettings();
    }, []);

    /**
     * Fetch lists from Mailchimp with search support
     * @param {string} searchTerm - Search term to filter lists
     * @param {number} offset - Offset for pagination
     * @param {boolean} reset - Whether to reset the list or append
     * @param {boolean} isLoadMore - Whether this is a "load more" action
     * @param {string} listIdToSelect - Optional list_id to select after fetching
     */
    const fetchLists = async (searchTerm = '', offset = 0, reset = false, isLoadMore = false, listIdToSelect = null) => {
        setListsLoading(true);

        // Track if we're searching for a specific list
        if (listIdToSelect && reset) {
            searchingForListIdRef.current = listIdToSelect;
            listSearchBatchCountRef.current = 0;
        }

        let params = {
            wlmi_nonce: appState.settings_nonce,
            action: "wlmi_get_lists",
            offset: offset,
            count: 100,
            search_term: searchTerm
        };

        try {
            let json = await postRequest(params);
            let resJSON = getJSONData(json.data);

            if (resJSON.success === true && resJSON.data) {
                const newResults = resJSON.data.results || [];
                const hasMore = resJSON.data.has_more || false;
                const nextOff = resJSON.data.next_offset || 0;
                const total = resJSON.data.total_items || 0;

                // Update lists
                if (reset) {
                    setLists(newResults);
                } else {
                    setLists(prevLists => [...prevLists, ...newResults]);
                }

                setTotalLists(total);
                setNextOffset(nextOff);
                setCurrentSearchTerm(searchTerm);

                // Set selected list if list_id exists (only on initial load or when explicitly provided)
                const listIdToCheck = listIdToSelect !== null ? listIdToSelect : (searchingForListIdRef.current || (reset && !searchTerm ? settings.list_id : null));
                
                // Check if we're looking for a specific list (either passed or from ref)
                const isSearchingForList = searchingForListIdRef.current !== null || (listIdToSelect !== null && reset && !searchTerm);
                
                if (isSearchingForList && listIdToCheck) {
                    const selected = newResults.find(list => list.value === listIdToCheck);
                    if (selected) {
                        setSelectedList(selected);
                        searchingForListIdRef.current = null; // Found it, stop searching
                        listSearchBatchCountRef.current = 0;
                    } else if (hasMore && listSearchBatchCountRef.current < 3) {
                        // If list not found in current batch but more available, continue searching
                        // Limit to 3 batches to avoid infinite loops
                        listSearchBatchCountRef.current++;
                        const listIdToContinueSearching = searchingForListIdRef.current || listIdToCheck;
                        setTimeout(() => {
                            fetchLists(searchTerm, nextOff, false, false, listIdToContinueSearching);
                        }, 100);
                    } else if (listSearchBatchCountRef.current >= 3) {
                        // Stop searching after 3 batches
                        searchingForListIdRef.current = null;
                        listSearchBatchCountRef.current = 0;
                    }
                }

                // CRITICAL: Recursive auto-fetch logic
                // If searching and no results found but more data available, automatically fetch next batch
                if (searchTerm && newResults.length === 0 && hasMore && !isLoadMore) {
                    setIsAutoFetching(true);
                    // Automatically fetch the next batch
                    setTimeout(() => {
                        fetchLists(searchTerm, nextOff, false, false);
                    }, 100); // Small delay to prevent overwhelming the server
                } else {
                    setIsAutoFetching(false);
                }
            } else {
                alertifyToast(resJSON.data?.message || "Failed to fetch lists", false);
                setIsAutoFetching(false);
            }
        } catch (e) {
            alertifyToast("Error fetching lists", false);
            setIsAutoFetching(false);
        } finally {
            setListsLoading(false);
        }
    };

    /**
     * Handle search from ListSelect component
     * @param {string} searchTerm - The search term
     * @param {boolean} isLoadMore - Whether this is a load more action (scroll to bottom)
     */
    const handleSearch = (searchTerm, isLoadMore = false) => {
        if (isLoadMore) {
            // Load more: append to existing results
            if (nextOffset < totalLists && !listsLoading) {
                fetchLists(currentSearchTerm, nextOffset, false, true);
            }
        } else {
            // New search: reset results
            fetchLists(searchTerm, 0, true, false);
        }
    };

    const saveSettings = (wlmi_nonce = appState.settings_nonce) => {
        setDisableSave(true);
        let params = {
            wlmi_nonce,
            action: "wlmi_launcher_save_settings",
        };
        params.settings = btoa(unescape(encodeURIComponent(JSON.stringify(settings))));

        postRequest(params).then((json) => {
            let resJSON = getJSONData(json.data);
            if (resJSON.success === true) {
                alertifyToast(resJSON.data.message);
                setErrorList([]);
                setErrors({});
                const hasApiKey = settings.api_key && settings.api_key.trim() !== "";
                setSettingsSaved(hasApiKey);
                getSettings();
            } else {
                setErrors(resJSON.data);
                errorDisplayer(resJSON.data, {setErrorList});
            }
            setDisableSave(false);
        }).catch(() => {
            setDisableSave(false);
        });
    }

    const handleTestConnection = async () => {
        setTestLoading(true);
        let params = {
            wlmi_nonce: appState.settings_nonce,
            action: "wlmi_test_connection",
            api_key: settings.api_key
        };

        try {
            let json = await postRequest(params);
            let resJSON = getJSONData(json.data);
            if (resJSON.success === true) {
                alertifyToast(resJSON.data.message);
                setIsConnected(true);
                // Don't fetch lists here - only fetch after settings are saved
                // The list selector will only be visible after settings are saved
            } else {
                alertifyToast(resJSON.data.message, false);
                setIsConnected(false);
            }
        } catch (e) {
            setIsConnected(false);
        }
        setTestLoading(false);
    }

    const isLicenseActive = licenseStatus === "active";

    return (
        <div className="w-full flex flex-col gap-y-2 items-start h-full">
            <TitleActionContainer 
                title={labels.settings?.title || "Mailchimp Settings"} 
                saveAction={() => saveSettings()}
                saveDisabled={disableSave}
            />

            <div className="flex gap-x-6 items-start w-full min-h-[590px]">
                <div className="w-full h-full flex flex-col border border-card_border rounded-xl bg-white p-6 overflow-y-auto">
                    {loading ? (
                        <div className="flex flex-col gap-y-4 w-full">
                            <ShimmerLoading height="h-8" width="w-1/4" />
                            <ShimmerLoading height="h-4" width="w-1/2" />
                            <div className="flex gap-x-4 mt-4">
                                <ShimmerLoading height="h-12" width="w-7/12" />
                                <ShimmerLoading height="h-12" width="w-2/12" />
                            </div>
                            <ShimmerLoading height="h-4" width="w-1/6" />
                            <div className="mt-5">
                                <ShimmerLoading height="h-4" width="w-1/5" />
                                <ShimmerLoading height="h-12" width="w-full" />
                                <ShimmerLoading height="h-3" width="w-2/5" />
                            </div>
                        </div>
                    ) : (
                        <React.Fragment>
                            {isLicenseActive ? (
                                <React.Fragment>
                                    <h4 className="text-dark font-semibold text-lg tracking-wide">
                                        {labels.settings?.title || "Mailchimp Settings"}
                                    </h4>
                                    <p className="text-sm text-light font-normal mt-2 2xl:mt-2.5">
                                        {labels.settings?.description || "You can find your API key in your Mailchimp account settings."}
                                    </p>

                                    <div className="flex flex-col w-74_% 2xl:w-7/12 mt-3 xl:mt-4 2xl:mt-5">
                                        <div className="flex items-center w-full gap-x-5">
                                            <div className="flex-1">
                                                <Input
                                                    id="api_key"
                                                    type="text"
                                                    value={settings.api_key || ""}
                                                    placeHolder={labels.settings?.placeholder || "Enter your Mailchimp API Key"}
                                                    border={`border-2 border-opacity-100 ${isConnected ? 'border-green-500' : 'border-red-600'}`}
                                                    textColor={isConnected ? 'text-green-600' : 'text-red-600'}
                                                    height="h-12"
                                                    onChange={(e) => {
                                                        setSettings({
                                                            ...settings,
                                                            api_key: e.target.value
                                                        });
                                                    }}
                                                    error={errorList.includes("api_key")}
                                                />
                                            </div>

                                            <div className="flex items-center">
                                                <Button
                                                    id="test_connection"
                                                    icon={
                                                        <i className="text-md text-white leading-0 antialiased wlr wlrf-save color-important" />
                                                    }
                                                    textStyle="text-white font-medium text-sm_14_l_20"
                                                    bgColor="bg-green-600"
                                                    others="tracking-wide h-12 flex items-center"
                                                    padding="px-5 py-3"
                                                    disabled={testLoading}
                                                    click={(e) => {
                                                        e.preventDefault();
                                                        handleTestConnection();
                                                    }}
                                                >
                                                    {labels.settings?.test_connection || "Test Connection"}
                                                </Button>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1 mt-2">
                                            <span className="text-sm text-gray-600">
                                                {labels.settings?.status || "Status"}:
                                            </span>
                                            <span className={`text-sm font-medium ${isConnected ? "text-green-600" : "text-red-600"}`}>
                                                {isConnected
                                                    ? (labels.settings?.active || "Active")
                                                    : (labels.settings?.inactive || "Inactive")}
                                            </span>
                                        </div>

                                        {/* List Selection */}
                                        {isConnected && settingsSaved && (
                                            <div className="flex flex-col w-full mt-5">
                                                <label className="text-dark font-medium text-sm mb-2">
                                                    {labels.settings?.list_label || "Select Mailchimp List"}
                                                </label>
                                                <ListSelect
                                                    id="mailchimp_list"
                                                    value={selectedList}
                                                    onChange={(selected) => {
                                                        setSelectedList(selected);
                                                        setSettings({
                                                            ...settings,
                                                            list_id: selected ? selected.value : ""
                                                        });
                                                    }}
                                                    onSearch={handleSearch}
                                                    options={lists}
                                                    hasMore={nextOffset < totalLists}
                                                    loading={listsLoading || isAutoFetching}
                                                    error={errorList.includes("list_id")}
                                                    placeholder={labels.settings?.list_placeholder || "Search or select a list"}
                                                    searchPlaceholder={labels.settings?.search_placeholder || "Type to search lists..."}
                                                    loadingMessage={isAutoFetching
                                                        ? (labels.settings?.searching_message || "Searching through lists...")
                                                        : (labels.settings?.loading_message || "Loading...")}
                                                    noOptionsMessage={labels.settings?.no_results_message || "No lists found"}
                                                    scrollForMoreMessage={labels.settings?.scroll_for_more_message || "Scroll for more..."}
                                                />
                                                <p className="text-xs text-light mt-1">
                                                    {labels.settings?.list_description || "Choose the Mailchimp list where customers will be added"}
                                                </p>
                                                {isAutoFetching && (
                                                    <p className="text-xs text-primary mt-1 font-medium">
                                                        🔍 {(labels.settings?.searching_progress_message || "Searching through %s lists...").replace('%s', totalLists)}
                                                    </p>
                                                )}
                                            </div>
                                        )}

                                        {/* Migration Choice Dropdown */}
                                        {isConnected && settingsSaved && settings.wlmi_request_migration_from_admin && settings.list_id && (
                                            <div className="flex flex-col w-full mt-5">
                                                <label className="text-dark font-medium text-sm mb-2">
                                                    {labels.settings?.migration_label || "Migration Choice"}
                                                </label>
                                                <DropdownWrapper
                                                    options={labels.settings?.migration_options || []}
                                                    value={settings.migration_choice || ''}
                                                    handleDropDownClick={(item) => {
                                                        setSettings({
                                                            ...settings,
                                                            migration_choice: item.value
                                                        });
                                                    }}
                                                    label={settings.migration_choice 
                                                        ? getChosenLabel(labels.settings?.migration_options || [], settings.migration_choice) || labels.settings?.migration_placeholder
                                                        : (labels.settings?.migration_placeholder || "Select migration choice")}
                                                    width="w-full"
                                                />
                                                <p className="text-xs text-light mt-1">
                                                    {labels.settings?.migration_description || "Choose your migration option"}
                                                </p>
                                            </div>
                                        )}

                                        {/* Migration Status Card */}
                                        {isConnected && settingsSaved && settings.list_id && migrationStatus && migrationStatus.state !== 'no_list' && (
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
                                                            <div className="flex items-center gap-2 text-sm">
                                                                <span className="text-red-600">
                                                                    {migrationStatus.errored_operations.toLocaleString()} {labels.settings?.migration_failed_ops || "failed operations detected."}
                                                                </span>
                                                                {migrationStatus.first_error_file_url && (
                                                                    <a 
                                                                        href={migrationStatus.first_error_file_url}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="text-primary hover:underline"
                                                                    >
                                                                        {labels.settings?.migration_download_error_file || "Download error file"}
                                                                    </a>
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
                                        )}
                                    </div>
                                </React.Fragment>
                            ) : (
                                <EmptyPage
                                    title={labels.common?.upgrade_text}
                                    description={
                                        labels.common?.license_required_description ||
                                        "Activate your license to configure the Mailchimp integration settings."
                                    }
                                    buttonText={labels.common?.buy_pro_button_text || "Buy Pro"}
                                />
                            )}
                        </React.Fragment>
                    )}
                 </div>
            </div>
        </div>
    );
};

export default Settings;
