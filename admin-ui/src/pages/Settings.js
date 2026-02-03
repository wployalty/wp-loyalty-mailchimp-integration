import React from 'react';
import TitleActionContainer from "../components/Common/TitleActionContainer";
import ShimmerLoading from "../components/Common/ShimmerLoading";
import ListSelect from "../components/Common/ListSelect";
import { CommonContext, UiLabelContext } from "../Context";
import {postRequest} from "../components/Common/postRequest";
import { alertifyToast, errorDisplayer, getJSONData } from "../helpers/utilities";
import Input from "../components/Common/Input";
import Button from "../components/Common/Button";

const Settings = () => {
    const {appState} = React.useContext(CommonContext);
    const labels = React.useContext(UiLabelContext);

    const [settings, setSettings] = React.useState({
        api_key: "",
        list_id: ""
    });
    const [loading, setLoading] = React.useState(true);
    const [testLoading, setTestLoading] = React.useState(false);
    const [errorList, setErrorList] = React.useState([]);
    const [errors, setErrors] = React.useState({});
    const [isConnected, setIsConnected] = React.useState(false);
    const [settingsSaved, setSettingsSaved] = React.useState(false);

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
                 
                 setSettings(loadedSettings);
                setIsConnected(loadedSettings.connected || false);
                // Only mark as saved if API key actually exists in saved settings
                // This prevents showing list selector when settings are deleted but defaults are returned
                const hasSavedApiKey = loadedSettings.api_key && loadedSettings.api_key.trim() !== "";
                setSettingsSaved(hasSavedApiKey);

                // If connected and settings are saved, fetch initial lists
                if (loadedSettings.connected && hasSavedApiKey) {
                    fetchLists('', 0, true, false, loadedSettings.list_id);
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

    const saveSettings = async (wlmi_nonce = appState.settings_nonce) => {
        let params = {
            wlmi_nonce,
            action: "wlmi_launcher_save_settings", 
        };
        params.settings = btoa(unescape(encodeURIComponent(JSON.stringify(settings))));
        
        let json = await postRequest(params);
        let resJSON = getJSONData(json.data);
        if (resJSON.success === true) {
            alertifyToast(resJSON.data.message);
            setErrorList([]);
            setErrors({});
            // Only mark as saved if API key exists in the settings being saved
            const hasApiKey = settings.api_key && settings.api_key.trim() !== "";
            setSettingsSaved(hasApiKey);
            // Reload settings to get the connected status and fetch lists if connected
            getSettings();
        } else {
            setErrors(resJSON.data);
            errorDisplayer(resJSON.data, {setErrorList});
        }
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

    return (
        <div className="w-full flex flex-col gap-y-2 items-start h-full">
            <TitleActionContainer 
                title={labels.settings?.title || "Mailchimp Settings"} 
                saveAction={() => saveSettings()}
            />

            <div className="flex gap-x-6 items-start w-full h-[590px]">
                <div className="w-full h-full flex flex-col border border-card_border rounded-xl bg-white p-6">
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
                                </div>
                        </React.Fragment>
                    )}
                 </div>
            </div>
        </div>
    );
};

export default Settings;
