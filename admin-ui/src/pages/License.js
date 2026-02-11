import React from "react";
import { CommonContext, UiLabelContext } from "../Context";
import { postRequest } from "../components/Common/postRequest";
import { alertifyToast, getJSONData } from "../helpers/utilities";
import Input from "../components/Common/Input";
import Button from "../components/Common/Button";
import ShimmerLoading from "../components/Common/ShimmerLoading";
import TitleActionContainer from "../components/Common/TitleActionContainer";

const License = () => {
    const { appState } = React.useContext(CommonContext);
    const labels = React.useContext(UiLabelContext);

    const [licenseKey, setLicenseKey] = React.useState("");
    const [licenseStatus, setLicenseStatus] = React.useState("inactive");
    const [loading, setLoading] = React.useState(true);
    const [processing, setProcessing] = React.useState(false);
    const [disableSave, setDisableSave] = React.useState(false);

    const statusIsActive = licenseStatus === "active";

    const getInitialData = async () => {
        setLoading(true);
        try {
            const params = {
                action: "wlmi_launcher_settings",
                wlmi_nonce: appState.settings_nonce,
            };
            const json = await postRequest(params);
            const resJSON = getJSONData(json.data);
            if (resJSON.success === true && resJSON.data) {
                const data = resJSON.data;
                setLicenseKey(data.license_key || "");
                setLicenseStatus(data.license_status || "inactive");
            }
        } catch (e) {
            // silent fail, will keep defaults
        } finally {
            setLoading(false);
        }
    };

    React.useEffect(() => {
        getInitialData();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleActivate = async () => {
        if (!licenseKey || licenseKey.trim() === "") {
            alertifyToast(
                labels.settings?.license_key_required ||
                "License key is required.",
                false
            );
            return;
        }
        setProcessing(true);
        const params = {
            action: "wlmi_activate_license",
            wlmi_nonce: appState.common_nonce,
            license_key: licenseKey,
        };
        try {
            const json = await postRequest(params);
            const resJSON = getJSONData(json.data);
            if (resJSON.success === true) {
                alertifyToast(resJSON.data?.message || "License activated.");
                setLicenseStatus(resJSON.data?.status || "active");
            } else {
                alertifyToast(
                    resJSON.data?.message || "License activation failed.",
                    false
                );
                setLicenseStatus(resJSON.data?.status || "inactive");
            }
        } catch (e) {
            alertifyToast("License activation failed.", false);
        }
        setProcessing(false);
    };

    const handleDeactivate = async () => {
        setProcessing(true);
        const params = {
            action: "wlmi_deactivate_license",
            wlmi_nonce: appState.common_nonce,
        };
        try {
            const json = await postRequest(params);
            const resJSON = getJSONData(json.data);
            if (resJSON.success === true) {
                alertifyToast(resJSON.data?.message || "License deactivated.");
                setLicenseStatus("inactive");
            } else {
                alertifyToast(
                    resJSON.data?.message || "License deactivation failed.",
                    false
                );
            }
        } catch (e) {
            alertifyToast("License deactivation failed.", false);
        }
        setProcessing(false);
    };

    const handleRefresh = async () => {
        if (!licenseKey || licenseKey.trim() === "") {
            alertifyToast(
                labels.settings?.license_key_required ||
                "License key is required.",
                false
            );
            return;
        }
        setProcessing(true);
        const params = {
            action: "wlmi_check_license_status",
            wlmi_nonce: appState.common_nonce,
            license_key: licenseKey,
        };
        try {
            const json = await postRequest(params);
            const resJSON = getJSONData(json.data);
            if (resJSON.success === true) {
                alertifyToast(resJSON.data?.message || "License status updated.");
                setLicenseStatus(resJSON.data?.status || "inactive");
            } else {
                alertifyToast(
                    resJSON.data?.message || "License status check failed.",
                    false
                );
            }
        } catch (e) {
            alertifyToast("License status check failed.", false);
        }
        setProcessing(false);
    };

    const currentLabels = labels.settings || {};

    const handleSaveLicense = async (wlmi_nonce = appState.settings_nonce) => {
        setDisableSave(true);
        const payload = {
            license_key: licenseKey || "",
        };
        const params = {
            action: "wlmi_launcher_save_settings",
            wlmi_nonce,
        };
        params.settings = btoa(
            unescape(encodeURIComponent(JSON.stringify(payload)))
        );

        try {
            const json = await postRequest(params);
            const resJSON = getJSONData(json.data);
            if (resJSON.success === true) {
                alertifyToast(resJSON.data?.message || "License saved.");
                if (resJSON.data?.license_status) {
                    setLicenseStatus(resJSON.data.license_status);
                }
                if (typeof resJSON.data?.license_key === "string") {
                    setLicenseKey(resJSON.data.license_key);
                }
            } else {
                alertifyToast(
                    resJSON.data?.message || "License not saved!",
                    false
                );
            }
        } catch (e) {
            alertifyToast("License not saved!", false);
        }
        setDisableSave(false);
    };

    return (
        <div className="w-full flex flex-col gap-y-2 items-start h-full">
            <TitleActionContainer
                title={currentLabels.license_title || "License"}
                saveAction={() => handleSaveLicense()}
                saveDisabled={disableSave}
            />

            <div className="flex gap-x-6 items-start w-full h-[560px] mt-3">
                <div className="w-full h-full flex flex-col border border-card_border rounded-xl bg-white p-6">
                    {loading ? (
                        <div className="flex flex-col gap-y-4 w-full">
                            <ShimmerLoading height="h-8" width="w-1/4" />
                            <ShimmerLoading height="h-4" width="w-1/2" />
                            <ShimmerLoading height="h-12" width="w-full" />
                        </div>
                    ) : (
                        <React.Fragment>
                            <h4 className="text-dark font-semibold text-lg tracking-wide">
                                {currentLabels.license_title || "License"}
                            </h4>
                            <p className="text-sm text-light font-normal mt-2 2xl:mt-2.5">
                                {currentLabels.license_description ||
                                    "Activate your license key to unlock all features of the Mailchimp integration."}
                            </p>

                            {/* Match Settings tab width/layout for the License row */}
                            <div className="flex flex-col w-74_% 2xl:w-7/12 mt-4">
                                <label className="text-dark font-medium text-sm mb-2">
                                    {currentLabels.license_key_label || "License Key"}
                                </label>
                                <div className="flex items-start w-full gap-x-5">
                                    {/* Left: License input + status + refresh (same pattern as WPLoyalty) */}
                                    <div className="flex-1 flex flex-col justify-center">
                                        <Input
                                            id="wlmi_license_key"
                                            type="text"
                                            value={licenseKey}
                                            placeHolder={
                                                currentLabels.license_key_placeholder ||
                                                "Enter your license key"
                                            }
                                            border={`border-2 border-opacity-100 ${
                                                statusIsActive ? "border-green-500" : "border-red-600"
                                            }`}
                                            textColor={statusIsActive ? "text-green-600" : "text-red-600"}
                                            height="h-12"
                                            onChange={(e) => setLicenseKey(e.target.value)}
                                        />
                                        <div className="flex items-center gap-1 mt-2">
                                            <span className="text-sm text-gray-600">
                                                {currentLabels.license_status_label || "Status"}:
                                            </span>
                                            <span
                                                className={`text-sm font-medium ${
                                                    statusIsActive ? "text-green-600" : "text-red-600"
                                                }`}
                                            >
                                                {statusIsActive
                                                    ? currentLabels.license_status_active || "Active"
                                                    : currentLabels.license_status_inactive || "Inactive"}
                                            </span>
                                            <i
                                                onClick={() => {
                                                    if (!processing) {
                                                    handleRefresh();
                                                    }
                                                }}
                                                className={`text-sm wlr wlrf-refresh cursor-pointer ${
                                                    processing ? "text-gray-400" : "text-primary"
                                                } ml-1`}
                                                title={
                                                    currentLabels.license_refresh_button ||
                                                    "Refresh status"
                                                }
                                            />
                                        </div>
                                    </div>

                                    {/* Right: Activate / Deactivate button aligned with input */}
                                    <div className="flex items-center gap-2">
                                        {!statusIsActive && (
                                            <Button
                                                id="wlmi_license_activate"
                                                icon={
                                                    <i className="text-md text-white leading-0 antialiased wlr wlrf-save color-important" />
                                                }
                                                textStyle="text-white font-medium text-sm_14_l_20"
                                                bgColor="bg-green-600"
                                                others="tracking-wide h-[48px] flex items-center"
                                                padding="px-5 py-3"
                                                disabled={processing}
                                                click={(e) => {
                                                    e.preventDefault();
                                                    handleActivate();
                                                }}
                                            >
                                                {currentLabels.license_activate_button || "Activate"}
                                            </Button>
                                        )}

                                        {statusIsActive && (
                                            <Button
                                                id="wlmi_license_deactivate"
                                                icon={
                                                    <i className="text-md text-white leading-0 antialiased wlr wlrf-save color-important" />
                                                }
                                                textStyle="text-white font-medium text-sm_14_l_20"
                                                bgColor="bg-red-600"
                                                others="tracking-wide h-[48px] flex items-center"
                                                padding="px-5 py-3"
                                                disabled={processing}
                                                click={(e) => {
                                                    e.preventDefault();
                                                    handleDeactivate();
                                                }}
                                            >
                                                {currentLabels.license_deactivate_button || "Deactivate"}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </React.Fragment>
                    )}
                </div>
            </div>
        </div>
    );
};

export default License;

