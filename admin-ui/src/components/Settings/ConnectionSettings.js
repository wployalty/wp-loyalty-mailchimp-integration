import React from 'react';
import { UiLabelContext } from "../../Context";
import Input from "../Common/Input";
import Button from "../Common/Button";
import { postRequest } from "../Common/postRequest";
import { getJSONData, alertifyToast } from "../../helpers/utilities";

const ConnectionSettings = ({ settings, setSettings, isConnected, setIsConnected, connectionLoading, setConnectionLoading, errorList, appState, fetchLists, setSavedListId, setSelectedList, setLists, setMigrationStatus, setErrorList, setErrors, getSettings }) => {
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
                // Re-fetch all settings to ensure everything is in sync
                getSettings();
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
        <div className="wlmi-flex wlmi-flex-col wlmi-w-74_% 2xl:wlmi-w-7/12 wlmi-mt-3 xl:wlmi-mt-4 2xl:wlmi-mt-5">
            <div className="wlmi-flex wlmi-items-center wlmi-w-full wlmi-gap-x-5">
                <div className="wlmi-flex-1">
                    <Input
                        id="api_key"
                        type="text"
                        value={settings.api_key || ""}
                        placeHolder={labels.settings?.placeholder || "Enter your Mailchimp API Key"}
                        border={`wlmi-border-2 wlmi-border-opacity-100 ${isConnected ? 'wlmi-border-green-500' : 'wlmi-border-red-600'}`}
                        textColor={isConnected ? 'wlmi-text-green-600' : 'wlmi-text-red-600'}
                        height="wlmi-h-12"
                        onChange={(e) => {
                            setSettings({
                                ...settings,
                                api_key: e.target.value,
                            });
                        }}
                        error={errorList.includes("api_key")}
                    />
                </div>

                <div className="wlmi-flex wlmi-items-center">
                    {!isConnected && (
                        <Button
                            id="connect_mailchimp"
                            icon={
                                <i className="wlmi-text-md wlmi-text-white leading-0 wlmi-antialiased wlr wlrf-save color-important" />
                            }
                            textStyle="wlmi-text-white wlmi-font-medium wlmi-text-sm_14_l_20"
                            bgColor="wlmi-bg-green-600"
                            others="wlmi-tracking-wide wlmi-h-12 wlmi-flex wlmi-items-center"
                            padding="wlmi-px-5 wlmi-py-3"
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
                                <i className="wlmi-text-md wlmi-text-white leading-0 wlmi-antialiased wlr wlrf-save color-important" />
                            }
                            textStyle="wlmi-text-white wlmi-font-medium wlmi-text-sm_14_l_20"
                            bgColor="wlmi-bg-red-600"
                            others="wlmi-tracking-wide wlmi-h-12 wlmi-flex wlmi-items-center"
                            padding="wlmi-px-5 wlmi-py-3"
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
            <div className="wlmi-flex wlmi-items-center wlmi-gap-1 wlmi-mt-2">
                <span className="wlmi-text-sm wlmi-text-gray-600">
                    {labels.settings?.status || "Status"}:
                </span>
                <span className={`wlmi-text-sm wlmi-font-medium ${isConnected ? "wlmi-text-green-600" : "wlmi-text-red-600"}`}>
                    {isConnected
                        ? (labels.settings?.active || "Active")
                        : (labels.settings?.inactive || "Inactive")}
                </span>
            </div>
        </div>
    );
};

export default ConnectionSettings;
