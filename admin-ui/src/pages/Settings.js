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

    // List selection state
    const [lists, setLists] = React.useState([]);
    const [listsLoading, setListsLoading] = React.useState(false);
    const [listOffset, setListOffset] = React.useState(0);
    const [totalLists, setTotalLists] = React.useState(0);
    const [selectedList, setSelectedList] = React.useState(null);

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

                // If connected, fetch lists
                if (loadedSettings.connected) {
                    fetchLists(0, true);
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

    const fetchLists = async (offset = 0, reset = false) => {
        setListsLoading(true);
        let params = {
            wlmi_nonce: appState.settings_nonce,
            action: "wlmi_get_lists",
            offset: offset,
            count: 25
        };

        try {
            let json = await postRequest(params);
            let resJSON = getJSONData(json.data);
            if (resJSON.success === true && resJSON.data) {
                const newLists = resJSON.data.lists || [];
                setLists(reset ? newLists : [...lists, ...newLists]);
                setTotalLists(resJSON.data.total_items || 0);
                setListOffset(offset + newLists.length);

                // Set selected list if list_id exists in settings
                if (settings.list_id && reset) {
                    const selected = newLists.find(list => list.value === settings.list_id);
                    if (selected) {
                        setSelectedList(selected);
                    }
                }
            } else {
                alertifyToast(resJSON.data?.message || "Failed to fetch lists", false);
            }
        } catch (e) {
            alertifyToast("Error fetching lists", false);
        }
        setListsLoading(false);
    };

    const handleLoadMore = () => {
        if (listOffset < totalLists && !listsLoading) {
            fetchLists(listOffset, false);
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
                // Fetch lists after successful connection
                fetchLists(0, true);
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
                                    {isConnected && (
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
                                                onLoadMore={handleLoadMore}
                                                options={lists}
                                                hasMore={listOffset < totalLists}
                                                loading={listsLoading}
                                                error={errorList.includes("list_id")}
                                                placeholder={labels.settings?.list_placeholder || "Select a list"}
                                            />
                                            <p className="text-xs text-light mt-1">
                                                {labels.settings?.list_description || "Choose the Mailchimp list where customers will be added"}
                                            </p>
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
