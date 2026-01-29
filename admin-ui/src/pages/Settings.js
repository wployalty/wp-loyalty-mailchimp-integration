import React from 'react';
import TitleActionContainer from "../components/Common/TitleActionContainer";
import {CommonContext, UiLabelContext} from "../Context";
import LoadingAnimation from "../components/Common/LoadingAnimation";
import {postRequest} from "../components/Common/postRequest";
import {alertifyToast, confirmAlert, errorDisplayer, getJSONData, getErrorMessage} from "../helpers/utilities";
import LabelInputContainer from "../components/Common/LabelInputContainer";

const Settings = () => {
    const {appState} = React.useContext(CommonContext);
    const labels = React.useContext(UiLabelContext);
    
    // Manage settings as an object for extensibility
    const [settings, setSettings] = React.useState({
        api_key: ""
    });
    const [loading, setLoading] = React.useState(true);
    const [errorList, setErrorList] = React.useState([]);
    const [errors, setErrors] = React.useState({});

    const getSettings = async (wlmi_nonce = appState.settings_nonce) => {
        let params = {
            action: "wlmi_launcher_settings",
            wlmi_nonce,
        };
        
        postRequest(params).then((json) => {
            let resJSON = getJSONData(json.data);
            if (resJSON.success === true && resJSON.data !== null) {
                 // Clean API key if it came from the old format or verify strict object structure
                 let loadedSettings = resJSON.data;
                 if (!loadedSettings.api_key) loadedSettings.api_key = ""; 
                 
                 setSettings(loadedSettings);
                 setLoading(false)
            } else {
                 setLoading(false);
            }
        })
    }

    React.useEffect(() => {
        getSettings();
    }, []);

    const handleChange = (e) => {
        const {name, value} = e.target;
        setSettings(prev => ({
            ...prev,
            [name]: value
        }));
    }

    const saveSettings = async (wlmi_nonce = appState.settings_nonce) => {
        let params = {
            wlmi_nonce,
            action: "wlmi_launcher_save_settings", 
        };
        // Encode settings object as base64 string
        params.settings = btoa(unescape(encodeURIComponent(JSON.stringify(settings))));
        
        let json = await postRequest(params);
        let resJSON = getJSONData(json.data);
        if (resJSON.success === true) {
            alertifyToast(resJSON.data.message);
            setErrorList([]);
            setErrors({});
        } else {
            setErrors(resJSON.data);
            errorDisplayer(resJSON.data, {setErrorList});
        }
    }



    return loading ? (<LoadingAnimation/>) : (
        <div className={`w-full flex flex-col gap-y-2 items-start h-full `}>
            <TitleActionContainer 
                title={labels.settings?.title || "Mailchimp Settings"} 
                saveAction={() => saveSettings()}
            />
            
            <div className={`flex gap-x-6 items-start w-full h-[590px]`}>
                 <div className={`w-full h-full flex flex-col border border-card_border rounded-xl bg-white p-6`}>
                    <div className="flex flex-col gap-4 max-w-xl">
                        <LabelInputContainer
                            label={labels.settings?.api_key || "Mailchimp API Key"}
                            value={settings.api_key}
                            onChange={(e) => setSettings(prev => ({...prev, api_key: e.target.value}))}
                            placeHolder={labels.settings?.placeholder || "Enter your Mailchimp API Key"}
                            error={errorList.includes("settings.api_key")}
                            error_message={errorList.includes("settings.api_key") && getErrorMessage(errors, "settings.api_key")}
                        />
                        <p className="text-light text-sm">
                            {labels.settings?.description || "You can find your API key in your Mailchimp account settings."}
                        </p>
                    </div>
                 </div>
            </div>
        </div>
    );
};

export default Settings;
