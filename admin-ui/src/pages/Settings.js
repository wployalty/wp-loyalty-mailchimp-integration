import React from 'react';
import TitleActionContainer from "../components/Common/TitleActionContainer";
import ShimmerLoading from "../components/Common/ShimmerLoading";
import { CommonContext, UiLabelContext } from "../Context";
import { postRequest } from "../components/Common/postRequest";
import { alertifyToast, errorDisplayer, getJSONData } from "../helpers/utilities";
import EmptyPage from "../components/Common/EmptyPage";
import ConnectionSettings from "../components/Settings/ConnectionSettings";
import ListSettings from "../components/Settings/ListSettings";
import MigrationStatus from "../components/Settings/MigrationStatus";

const Settings = () => {
    const {appState} = React.useContext(CommonContext);
    const labels = React.useContext(UiLabelContext);

    const [settings, setSettings] = React.useState({
        api_key: "",
        list_id: "",
        migration_choice: ""
    });
    const [loading, setLoading] = React.useState(true);
    const [connectionLoading, setConnectionLoading] = React.useState(false);
    const [disableSave, setDisableSave] = React.useState(false);
    const [errorList, setErrorList] = React.useState([]);
    const [errors, setErrors] = React.useState({});
    const [isConnected, setIsConnected] = React.useState(false);
    const [savedListId, setSavedListId] = React.useState("");
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
            action: "wlmi_admin_settings",
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
                 setSettings(loadedSettings);
                setLicenseStatus(loadedSettings.license_status || "inactive");
                setIsConnected(loadedSettings.connected || false);
                setSavedListId(loadedSettings.list_id || "");

                // If connected, fetch initial lists
                if (loadedSettings.connected) {
                    fetchLists('', 0, true, false, loadedSettings.list_id);
                } else {
                    setLists([]);
                    setSelectedList(null);
                }

                // If list_id is configured, fetch migration status
                if (loadedSettings.list_id && loadedSettings.list_id.trim() !== "") {
                    fetchMigrationStatus();
                } else {
                    setMigrationStatus(null);
                }
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
        if (!isConnected) {
            alertifyToast(labels.settings?.connect_required || "Connect Mailchimp before saving.", false);
            return;
        }
        if (!settings.list_id) {
            alertifyToast(labels.settings?.list_required || "Please select a Mailchimp list.", false);
            return;
        }
        const listTransition = settings.list_id !== savedListId;
        if (listTransition && !settings.migration_choice) {
            setErrorList(["migration_choice"]);
            alertifyToast(labels.settings?.migration_choice_required || "Please choose whether to migrate existing users.", false);
            return;
        }

        setDisableSave(true);
        let params = {
            wlmi_nonce,
            action: "wlmi_save_settings",
        };
        params.settings = btoa(unescape(encodeURIComponent(JSON.stringify(settings))));

        postRequest(params).then((json) => {
            let resJSON = getJSONData(json.data);
            if (resJSON.success === true) {
                alertifyToast(resJSON.data.message);
                setErrorList([]);
                setErrors({});
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

    const isLicenseActive = licenseStatus === "active";
    const listTransition = savedListId !== settings.list_id && '' !== settings.list_id;
    const saveDisabled = disableSave || !isConnected || !settings.list_id || (listTransition && !settings.migration_choice);

    return (
        <div className="w-full flex flex-col gap-y-2 items-start h-full">
            <TitleActionContainer 
                title={labels.settings?.title || "Mailchimp Settings"} 
                saveAction={() => saveSettings()}
                saveDisabled={saveDisabled}
            />

            <div className="flex gap-x-6 items-stretch w-full min-h-[590px]">
                <div className="w-full flex-1 min-h-full flex flex-col border border-card_border rounded-xl bg-white p-6 overflow-y-auto">
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
                                        <ConnectionSettings
                                            settings={settings}
                                            setSettings={setSettings}
                                            isConnected={isConnected}
                                            setIsConnected={setIsConnected}
                                            connectionLoading={connectionLoading}
                                            setConnectionLoading={setConnectionLoading}
                                            errorList={errorList}
                                            appState={appState}
                                            fetchLists={fetchLists}
                                            setSavedListId={setSavedListId}
                                            setSelectedList={setSelectedList}
                                            setLists={setLists}
                                            setMigrationStatus={setMigrationStatus}
                                            setErrorList={setErrorList}
                                            setErrors={setErrors}
                                        />

                                        <ListSettings
                                            isConnected={isConnected}
                                            settings={settings}
                                            setSettings={setSettings}
                                            selectedList={selectedList}
                                            setSelectedList={setSelectedList}
                                            handleSearch={handleSearch}
                                            lists={lists}
                                            nextOffset={nextOffset}
                                            totalLists={totalLists}
                                            listsLoading={listsLoading}
                                            isAutoFetching={isAutoFetching}
                                            errorList={errorList}
                                            setErrorList={setErrorList}
                                            listTransition={listTransition}
                                        />

                                        <MigrationStatus
                                            isConnected={isConnected}
                                            settings={settings}
                                            migrationStatus={migrationStatus}
                                            migrationStatusLoading={migrationStatusLoading}
                                            fetchMigrationStatus={fetchMigrationStatus}
                                            appState={appState}
                                        />
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
