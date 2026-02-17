import React from 'react';
import { UiLabelContext } from "../Context";
import Input from "../components/Common/Input";
import Button from "../components/Common/Button";
import { postRequest } from "../components/Common/postRequest";
import { getJSONData, alertifyToast } from "../helpers/utilities";

const ConnectionSettings = ({ settings, setSettings, isConnected, setIsConnected, connectionLoading, setConnectionLoading, errorList, appState, fetchLists, setSavedListId, setSelectedList, setLists, setMigrationStatus, setErrorList, setErrors }) => {
    const labels = React.useContext(UiLabelContext);

    const handleConnect = async () => {
        setConnectionLoading(true);
        let params = {
            wlmi_nonce: appState.settings_nonce,
            action: "wlmi_connect_mailchimp",
            api_key: settings.api_key
        };

        try {
            let json = await postRequest(params);
            let resJSON = getJSONData(json.data);
            if (resJSON.success === true) {
                alertifyToast(resJSON.data.message);
                setIsConnected(true);
                fetchLists('', 0, true, false, settings.list_id || null);
            } else {
                alertifyToast(resJSON.data.message, false);
                setIsConnected(false);
            }
        } catch (e) {
            setIsConnected(false);
        }
        setConnectionLoading(false);
    }

    const handleDisconnect = async () => {
        setConnectionLoading(true);
        let params = {
            wlmi_nonce: appState.settings_nonce,
            action: "wlmi_disconnect_mailchimp",
        };
        try {
            let json = await postRequest(params);
            let resJSON = getJSONData(json.data);
            if (resJSON.success === true) {
                alertifyToast(resJSON.data.message);
                setIsConnected(false);
                setSavedListId("");
                setSelectedList(null);
                setLists([]);
                setMigrationStatus(null);
                setErrorList([]);
                setErrors({});
                setSettings((prev) => ({
                    ...prev,
                    api_key: "",
                    list_id: "",
                    migration_choice: ""
                }));
            } else {
                alertifyToast(resJSON.data?.message || "Disconnect failed", false);
            }
        } catch (e) {
            alertifyToast("Disconnect failed", false);
        }
        setConnectionLoading(false);
    }

    return (
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
                                api_key: e.target.value,
                                list_id: "",
                                migration_choice: ""
                            });
                            setIsConnected(false);
                            setSavedListId("");
                            setSelectedList(null);
                            setLists([]);
                            setMigrationStatus(null);
                        }}
                        error={errorList.includes("api_key")}
                    />
                </div>

                <div className="flex items-center">
                    {!isConnected && (
                        <Button
                            id="connect_mailchimp"
                            icon={
                                <i className="text-md text-white leading-0 antialiased wlr wlrf-save color-important" />
                            }
                            textStyle="text-white font-medium text-sm_14_l_20"
                            bgColor="bg-green-600"
                            others="tracking-wide h-12 flex items-center"
                            padding="px-5 py-3"
                            disabled={connectionLoading}
                            click={(e) => {
                                e.preventDefault();
                                handleConnect();
                            }}
                        >
                            {labels.settings?.connect || "Connect"}
                        </Button>
                    )}
                    {isConnected && (
                        <Button
                            id="disconnect_mailchimp"
                            icon={
                                <i className="text-md text-white leading-0 antialiased wlr wlrf-save color-important" />
                            }
                            textStyle="text-white font-medium text-sm_14_l_20"
                            bgColor="bg-red-600"
                            others="tracking-wide h-12 flex items-center"
                            padding="px-5 py-3"
                            disabled={connectionLoading}
                            click={(e) => {
                                e.preventDefault();
                                handleDisconnect();
                            }}
                        >
                            {labels.settings?.disconnect || "Disconnect"}
                        </Button>
                    )}
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
        </div>
    );
};

export default ConnectionSettings;
